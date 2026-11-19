<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\Submission;
use App\Models\User;
use App\Support\FormatNotifikasi;
use App\Support\JumlahNotifikasi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Logika data Pusat Notifikasi dosen.
 *
 * Hanya pengumpulan tugas pada mata kuliah yang diampu dosen yang sedang login
 * (courses.lecturer_id). Data diturunkan dari tabel yang sudah ada. Status
 * dibaca disimpan terpisah: satu timestamp per user ("tandai semua dibaca") dan
 * tabel notifikasi_admin_dibaca ("tandai dibaca" satu per satu).
 *
 * Controller hanya membaca request dan menampilkan view; seluruh query,
 * agregasi, dan pembentukan item ada di sini.
 */
class DosenNotifikasiService
{
    public const PER_PAGE = 8;

    public const TAB = [
        'semua' => 'Semua Pengumpulan',
        'menunggu' => 'Menunggu Penilaian',
        'terlambat' => 'Terlambat',
        'dinilai' => 'Sudah Dinilai',
    ];

    /** Mata kuliah milik dosen ini (batas semua data di halaman). */
    public function mataKuliah(User $dosen): Collection
    {
        return Course::where('lecturer_id', $dosen->id)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);
    }

    /** @return int[] */
    public function idMataKuliah(Collection $mataKuliah): array
    {
        return $mataKuliah->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /*
    |--------------------------------------------------------------------------
    | Feed + hitungan tab (mengikuti pencarian, mata kuliah, dan periode)
    |--------------------------------------------------------------------------
    */

    /**
     * @param  int[]  $mkIds
     * @return array{tabs: array, total: int, items: Collection}
     */
    public function feed(User $dosen, array $mkIds, string $tab, string $periode, string $q, ?int $mk, int $halaman): array
    {
        $sejak = FormatNotifikasi::sejak($periode);

        // SATU query dengan 4 subquery count, bukan 4 query terpisah.
        $hitung = DB::query();
        foreach (array_keys(self::TAB) as $kunci) {
            $hitung->selectSub(
                $this->terapkanTab($this->kumpulFeed($dosen, $mkIds, $sejak, $q, $mk), $kunci)->selectRaw('count(*)'),
                "n_{$kunci}"
            );
        }
        $hasilHitung = $hitung->first();

        $tabs = [];
        foreach (self::TAB as $kunci => $label) {
            $tabs[$kunci] = [
                'label' => $label,
                'jumlah' => (int) $hasilHitung->{"n_{$kunci}"},
            ];
        }

        // Total sudah diketahui dari hitungan tab di atas, jadi tidak perlu
        // query COUNT kedua seperti yang dilakukan paginate().
        $items = $this->terapkanTab($this->kumpulFeed($dosen, $mkIds, $sejak, $q, $mk), $tab)
            ->select(
                'submissions.*',
                'assignments.title as assignment_title',
                'assignments.due_at as assignment_due',
                'courses.id as course_id',
                'courses.code as course_code',
                'courses.name as course_name',
                'mahasiswa.name as actor_name'
            )
            ->selectRaw('(select max(version_number) from submission_revisions where submission_revisions.submission_id = submissions.id) as versi')
            ->withExists('grade as sudah_dinilai')
            ->orderByDesc('submissions.submitted_at')
            ->forPage($halaman, self::PER_PAGE)
            ->get()
            ->map(fn ($r) => $this->bentukItem($r));

        return [
            'tabs' => $tabs,
            'total' => $tabs[$tab]['jumlah'],
            'items' => $items,
        ];
    }

    /** Jumlah pengumpulan yang belum dibaca dosen ini (angka di header halaman). */
    public function belumDibaca(User $dosen, array $mkIds): int
    {
        return $this->kumpulFeed($dosen, $mkIds, null, '', null)->count();
    }

    /*
    |--------------------------------------------------------------------------
    | Aksi tandai dibaca
    |--------------------------------------------------------------------------
    */

    /**
     * Menandai satu pengumpulan sebagai sudah dibaca. Hanya pengumpulan di mata
     * kuliah yang diampu; nilai dan berkas mahasiswa tidak berubah.
     *
     * @throws ModelNotFoundException bila bukan milik dosen ini (jadi 404)
     */
    public function tandaiDibaca(User $dosen, int $id): void
    {
        $milikDosen = Submission::query()
            ->join('assignments', 'assignments.id', '=', 'submissions.assignment_id')
            ->join('courses', 'courses.id', '=', 'assignments.course_id')
            ->where('courses.lecturer_id', $dosen->id)
            ->where('submissions.id', $id)
            ->exists();

        if (! $milikDosen) {
            throw new ModelNotFoundException();
        }

        DB::table('notifikasi_admin_dibaca')->insertOrIgnore([
            'user_id' => $dosen->id,
            'jenis' => 'pengumpulan',
            'item_id' => $id,
            'created_at' => now(),
        ]);
    }

    /**
     * Menandai semua pengumpulan yang ada sebagai sudah dibaca. Hanya satu
     * timestamp di user yang login yang berubah.
     *
     * @return int jumlah pengumpulan yang ditandai
     */
    public function tandaiSemuaDibaca(User $dosen): int
    {
        $mkIds = $this->idMataKuliah($this->mataKuliah($dosen));
        $jumlah = $this->belumDibaca($dosen, $mkIds);

        $dosen->forceFill(['notifications_read_at' => JumlahNotifikasi::batasTandaiSemuaDosen($dosen)])->save();

        return $jumlah;
    }

    /*
    |--------------------------------------------------------------------------
    | Query dasar: pengumpulan pada mata kuliah milik dosen ini
    |--------------------------------------------------------------------------
    */
    private function kumpulBase(array $mkIds, ?Carbon $sejak, string $q, ?int $mk): Builder
    {
        $query = Submission::query()
            ->join('assignments', 'assignments.id', '=', 'submissions.assignment_id')
            ->join('courses', 'courses.id', '=', 'assignments.course_id')
            ->join('users as mahasiswa', 'mahasiswa.id', '=', 'submissions.user_id')
            ->whereIn('courses.id', $mkIds);

        if ($mk !== null) {
            $query->where('courses.id', $mk);
        }

        if ($sejak) {
            $query->where('submissions.submitted_at', '>=', $sejak);
        }

        if ($q !== '') {
            $pola = "%{$q}%";
            $query->where(function ($w) use ($pola) {
                $w->where('assignments.title', 'like', $pola)
                    ->orWhere('courses.name', 'like', $pola)
                    ->orWhere('courses.code', 'like', $pola)
                    ->orWhere('mahasiswa.name', 'like', $pola);
            });
        }

        return $query;
    }

    /**
     * kumpulBase() tanpa pengumpulan yang sudah dibaca. Dipakai daftar dan
     * hitungan tab. Kartu ringkasan dan antrean penilaian tetap memakai
     * kumpulBase() karena itu statistik, bukan notifikasi.
     */
    private function kumpulFeed(User $dosen, array $mkIds, ?Carbon $sejak, string $q, ?int $mk): Builder
    {
        $query = $this->kumpulBase($mkIds, $sejak, $q, $mk);

        if ($dibacaSemua = $dosen->notifications_read_at) {
            $query->where('submissions.submitted_at', '>', $dibacaSemua);
        }

        return JumlahNotifikasi::tanpaDitandai($query, (int) $dosen->id, 'pengumpulan', 'submissions.id');
    }

    private function terapkanTab(Builder $query, string $tab): Builder
    {
        return match ($tab) {
            'menunggu' => $query->whereDoesntHave('grade'),
            'dinilai' => $query->whereHas('grade'),
            'terlambat' => $query->where('submissions.is_late', true),
            default => $query,
        };
    }

    /** Satu baris pengumpulan -> bentuk seragam untuk view. */
    private function bentukItem($r): array
    {
        $at = Carbon::parse($r->submitted_at);
        $telat = (bool) $r->is_late;
        $dinilai = (bool) $r->sudah_dinilai;
        $versi = (int) $r->versi;
        $lokal = $at->copy()->locale('id');

        $chips = [
            $telat ? ['Terlambat', 'bad'] : ['Tepat Waktu', 'ok'],
            $dinilai ? ['Sudah Dinilai', 'info'] : ['Menunggu Nilai', 'warn'],
        ];

        if ($versi > 1) {
            array_unshift($chips, ['Revisi v' . $versi, 'tone']);
        }

        return [
            'id' => $r->id,
            'assignment_id' => $r->assignment_id,
            'course_id' => $r->course_id,
            'course_code' => $r->course_code,
            'course_name' => $r->course_name,
            'actor' => $r->actor_name,
            'judul' => $r->assignment_title,
            'nada' => $telat ? 'terlambat' : 'pengumpulan',
            'dinilai' => $dinilai,
            'chips' => $chips,
            'meta' => [
                'Batas ' . Carbon::parse($r->assignment_due)->locale('id')->translatedFormat('j M Y, H:i'),
                'Pukul ' . $at->format('H:i'),
            ],
            'catatan' => $r->note ? Str::limit($r->note, 160) : null,
            'lampiran' => ['nama' => $r->original_name, 'info' => FormatNotifikasi::ukuran((int) $r->file_size)],
            'at' => $at,
            'lengkap' => $lokal->translatedFormat('j F Y, H:i'),
            'relatif' => $lokal->diffForHumans(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Kartu ringkasan (seluruh mata kuliah milik dosen, tidak ikut filter)
    |--------------------------------------------------------------------------
    */
    public function ringkasan(array $mkIds): array
    {
        $dasar = fn () => $this->kumpulBase($mkIds, null, '', null);
        $hariIni = now()->startOfDay();

        $tugasAktif = fn () => Assignment::whereIn('course_id', $mkIds)
            ->where('status', 'published')
            ->where('due_at', '>=', now());

        // SATU query dengan 7 subquery scalar, bukan 5 count() + 1 pluck terpisah.
        $r = DB::query()
            ->selectSub($dasar()->selectRaw('count(*)'), 'total')
            ->selectSub($dasar()->whereDoesntHave('grade')->selectRaw('count(*)'), 'belum')
            ->selectSub(
                $dasar()->whereDoesntHave('grade')->where('submissions.is_late', true)->selectRaw('count(*)'),
                'belum_telat'
            )
            ->selectSub($dasar()->where('submissions.submitted_at', '>=', $hariIni)->selectRaw('count(*)'), 'kumpul_hari_ini')
            ->selectSub(
                $dasar()->where('submissions.submitted_at', '>=', $hariIni)->where('submissions.is_late', true)->selectRaw('count(*)'),
                'telat_hari_ini'
            )
            ->selectSub($tugasAktif()->selectRaw('count(*)'), 'tugas_aktif')
            ->selectSub($tugasAktif()->selectRaw('count(distinct course_id)'), 'tugas_aktif_mk')
            ->first();

        $total = (int) $r->total;
        $belum = (int) $r->belum;
        $kumpulHariIni = (int) $r->kumpul_hari_ini;
        $telatHariIni = (int) $r->telat_hari_ini;

        return [
            'menunggu' => $belum,
            'menungguTelat' => (int) $r->belum_telat,
            'kumpulHariIni' => $kumpulHariIni,
            'telatHariIni' => $telatHariIni,
            'tepatHariIni' => $kumpulHariIni > 0
                ? (int) round((($kumpulHariIni - $telatHariIni) / $kumpulHariIni) * 100)
                : null,
            'total' => $total,
            'dinilai' => $total - $belum,
            'persenDinilai' => $total > 0 ? (int) round((($total - $belum) / $total) * 100) : 0,
            'tugasAktif' => (int) $r->tugas_aktif,
            'tugasAktifMk' => (int) $r->tugas_aktif_mk,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Antrean penilaian per mata kuliah (yang paling banyak belum dinilai di atas)
    |--------------------------------------------------------------------------
    */
    public function antreanPenilaian(Collection $mataKuliah, array $mkIds): Collection
    {
        // SATU query: total pengumpulan dan yang belum dinilai per mata kuliah
        // dihitung bersamaan (sum kondisional), bukan dua query groupBy terpisah.
        $perMk = $this->kumpulBase($mkIds, null, '', null)
            ->selectRaw('assignments.course_id as cid')
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when exists (select 1 from grades where grades.submission_id = submissions.id) then 0 else 1 end) as belum')
            ->groupBy('assignments.course_id')
            ->get()
            ->keyBy('cid');

        return $mataKuliah
            ->map(function ($c) use ($perMk) {
                $total = (int) ($perMk[$c->id]->total ?? 0);
                $belum = (int) ($perMk[$c->id]->belum ?? 0);
                $persen = $total > 0 ? (int) round((($total - $belum) / $total) * 100) : 0;

                return [
                    'id' => $c->id,
                    'kode' => $c->code,
                    'nama' => $c->name,
                    'total' => $total,
                    'belum' => $belum,
                    'dinilai' => $total - $belum,
                    'persen' => $persen,
                    'level' => FormatNotifikasi::level($persen),
                ];
            })
            ->filter(fn (array $c) => $c['total'] > 0)
            ->sortByDesc('belum')
            ->take(4)
            ->values();
    }

    /*
    |--------------------------------------------------------------------------
    | Batas waktu tugas terdekat di mata kuliah milik dosen ini
    |--------------------------------------------------------------------------
    */
    public function tenggatTerdekat(array $mkIds): Collection
    {
        // SATU query: jumlah mahasiswa terdaftar ikut dihitung lewat subquery,
        // bukan query withCount terpisah. Mahasiswa yang sudah dihapus (soft delete)
        // tetap tidak dihitung, sama seperti relasi students().
        return Assignment::query()
            ->where('assignments.status', 'published')
            ->where('assignments.due_at', '>=', now())
            ->whereIn('assignments.course_id', $mkIds)
            ->join('courses', 'courses.id', '=', 'assignments.course_id')
            ->select(
                'assignments.id',
                'assignments.title',
                'assignments.due_at',
                'assignments.course_id',
                'courses.code as course_code'
            )
            ->selectRaw('(select count(*) from submissions where submissions.assignment_id = assignments.id) as terkumpul')
            ->selectRaw('(select count(*) from users inner join course_user on users.id = course_user.user_id where course_user.course_id = assignments.course_id and users.deleted_at is null) as jumlah_mahasiswa')
            ->orderBy('assignments.due_at')
            ->limit(4)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'judul' => $r->title,
                'kode' => $r->course_code,
                'terkumpul' => (int) $r->terkumpul,
                'mahasiswa' => (int) $r->jumlah_mahasiswa,
            ] + FormatNotifikasi::tenggat($r->due_at));
    }
}