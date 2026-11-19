<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\Material;
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
 * Logika data Pusat Notifikasi admin.
 *
 * Feed tidak memakai tabel notifications bawaan Laravel: aktivitas admin
 * (materi diunggah, tugas terbit, pengumpulan masuk) dibentuk langsung dari
 * tabel materials, assignments, dan submissions, sehingga selalu sesuai data
 * terbaru dan tidak perlu menulis baris notifikasi untuk setiap event. Status
 * dibaca disimpan di users.notifications_read_at ("tandai semua") dan tabel
 * notifikasi_admin_dibaca ("tandai satu per satu").
 *
 * Controller hanya membaca request dan menampilkan view; seluruh query,
 * agregasi, dan pembentukan item ada di sini.
 */
class AdminNotifikasiService
{
    public const PER_PAGE = 8;

    public const TAB = [
        'semua' => 'Semua Aktivitas',
        'materi-tugas' => 'Materi & Tugas',
        'pengumpulan' => 'Pengumpulan',
        'terlambat' => 'Terlambat',
    ];

    /*
    |--------------------------------------------------------------------------
    | Feed + hitungan tab
    |--------------------------------------------------------------------------
    */

    /**
     * @return array{tabs: array, total: int, halamanAktif: int, items: Collection}
     */
    public function feed(User $admin, string $tab, string $periode, string $q, int $halamanDiminta): array
    {
        $sejak = FormatNotifikasi::sejak($periode);

        // SATU query dengan 4 subquery count, bukan 4 query terpisah.
        $hitung = DB::query()
            ->selectSub($this->materiBase($admin, $sejak, $q)->selectRaw('count(*)'), 'n_materi')
            ->selectSub($this->tugasBase($admin, $sejak, $q)->selectRaw('count(*)'), 'n_tugas')
            ->selectSub($this->kumpulBase($admin, $sejak, $q)->selectRaw('count(*)'), 'n_kumpul')
            ->selectSub(
                $this->kumpulBase($admin, $sejak, $q)->where('submissions.is_late', true)->selectRaw('count(*)'),
                'n_terlambat'
            )
            ->first();

        $nMateri = (int) $hitung->n_materi;
        $nTugas = (int) $hitung->n_tugas;
        $nKumpul = (int) $hitung->n_kumpul;
        $nTerlambat = (int) $hitung->n_terlambat;

        $jumlah = [
            'semua' => $nMateri + $nTugas + $nKumpul,
            'materi-tugas' => $nMateri + $nTugas,
            'pengumpulan' => $nKumpul,
            'terlambat' => $nTerlambat,
        ];

        $tabs = [];
        foreach (self::TAB as $kunci => $label) {
            $tabs[$kunci] = ['label' => $label, 'jumlah' => $jumlah[$kunci]];
        }

        // Ambil secukupnya dari tiap sumber, gabung, urutkan, potong ke halaman aktif.
        $total = $jumlah[$tab];
        $halamanTerakhir = max(1, (int) ceil($total / self::PER_PAGE));
        $halamanAktif = min($halamanTerakhir, max(1, $halamanDiminta));
        $ambil = $halamanAktif * self::PER_PAGE;

        $item = collect();

        if (in_array($tab, ['semua', 'materi-tugas'], true)) {
            $item = $item->merge($this->ambilMateri($admin, $sejak, $q, $ambil));
            $item = $item->merge($this->ambilTugas($admin, $sejak, $q, $ambil));
        }

        if (in_array($tab, ['semua', 'pengumpulan', 'terlambat'], true)) {
            $item = $item->merge($this->ambilKumpul($admin, $sejak, $q, $ambil, $tab === 'terlambat'));
        }

        $halamanIni = $item
            ->sortByDesc(fn (array $i) => $i['at']->getTimestamp())
            ->values()
            ->slice(($halamanAktif - 1) * self::PER_PAGE, self::PER_PAGE)
            ->values();

        return [
            'tabs' => $tabs,
            'total' => $total,
            'halamanAktif' => $halamanAktif,
            'items' => $halamanIni,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Aksi tandai dibaca
    |--------------------------------------------------------------------------
    */

    /**
     * Menandai semua aktivitas yang ada sebagai sudah dibaca. Hanya satu
     * timestamp di user yang login yang berubah.
     *
     * @return int jumlah aktivitas yang ditandai
     */
    public function tandaiSemuaDibaca(User $admin): int
    {
        $jumlah = JumlahNotifikasi::adminBelumDibaca($admin);

        $admin->forceFill(['notifications_read_at' => JumlahNotifikasi::batasTandaiSemuaAdmin()])->save();

        return $jumlah;
    }

    /**
     * Menandai satu aktivitas sebagai sudah dibaca (hanya untuk admin ini).
     * Tidak mengubah data sumbernya.
     *
     * @param  string  $jenis  'materi' | 'tugas' | 'pengumpulan'
     *
     * @throws ModelNotFoundException bila jenis tidak dikenal atau aktivitasnya tidak ada (jadi 404)
     */
    public function tandaiDibaca(User $admin, string $jenis, int $id): void
    {
        $sumber = match ($jenis) {
            'materi' => Material::query(),
            'tugas' => Assignment::query()->where('status', 'published'),
            'pengumpulan' => Submission::query(),
            default => throw new ModelNotFoundException(),
        };

        $sumber->whereKey($id)->firstOrFail();

        DB::table('notifikasi_admin_dibaca')->insertOrIgnore([
            'user_id' => $admin->id,
            'jenis' => $jenis,
            'item_id' => $id,
            'created_at' => now(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Query dasar (join + filter), dipakai untuk menghitung dan mengambil data
    |--------------------------------------------------------------------------
    */

    private function materiBase(User $admin, ?Carbon $sejak, string $q): Builder
    {
        $query = Material::query()
            ->join('courses', 'courses.id', '=', 'materials.course_id')
            ->join('users as pengunggah', 'pengunggah.id', '=', 'materials.uploaded_by');

        if ($sejak) {
            $query->where('materials.created_at', '>=', $sejak);
        }

        if ($q !== '') {
            $pola = "%{$q}%";
            $query->where(function ($w) use ($pola) {
                $w->where('materials.title', 'like', $pola)
                    ->orWhere('courses.name', 'like', $pola)
                    ->orWhere('courses.code', 'like', $pola)
                    ->orWhere('pengunggah.name', 'like', $pola);
            });
        }

        // Aktivitas yang sudah ditandai dibaca (satu per satu atau sekaligus) tidak ditampilkan lagi.
        if ($dibacaSemua = JumlahNotifikasi::batasDibacaAdmin($admin)) {
            $query->where('materials.created_at', '>', $dibacaSemua);
        }
        JumlahNotifikasi::tanpaDitandai($query, (int) $admin->id, 'materi', 'materials.id');

        return $query;
    }

    private function tugasBase(User $admin, ?Carbon $sejak, string $q): Builder
    {
        // Draf belum dilihat mahasiswa, jadi tidak dianggap aktivitas.
        $query = Assignment::query()
            ->where('assignments.status', 'published')
            ->join('courses', 'courses.id', '=', 'assignments.course_id')
            ->join('users as pembuat', 'pembuat.id', '=', 'assignments.created_by');

        if ($sejak) {
            $query->where('assignments.created_at', '>=', $sejak);
        }

        if ($q !== '') {
            $pola = "%{$q}%";
            $query->where(function ($w) use ($pola) {
                $w->where('assignments.title', 'like', $pola)
                    ->orWhere('courses.name', 'like', $pola)
                    ->orWhere('courses.code', 'like', $pola)
                    ->orWhere('pembuat.name', 'like', $pola);
            });
        }

        // Aktivitas yang sudah ditandai dibaca (satu per satu atau sekaligus) tidak ditampilkan lagi.
        if ($dibacaSemua = JumlahNotifikasi::batasDibacaAdmin($admin)) {
            $query->where('assignments.created_at', '>', $dibacaSemua);
        }
        JumlahNotifikasi::tanpaDitandai($query, (int) $admin->id, 'tugas', 'assignments.id');

        return $query;
    }

    private function kumpulBase(User $admin, ?Carbon $sejak, string $q): Builder
    {
        $query = Submission::query()
            ->join('assignments', 'assignments.id', '=', 'submissions.assignment_id')
            ->join('courses', 'courses.id', '=', 'assignments.course_id')
            ->join('users as mahasiswa', 'mahasiswa.id', '=', 'submissions.user_id');

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

        // Aktivitas yang sudah ditandai dibaca (satu per satu atau sekaligus) tidak ditampilkan lagi.
        if ($dibacaSemua = JumlahNotifikasi::batasDibacaAdmin($admin)) {
            $query->where('submissions.submitted_at', '>', $dibacaSemua);
        }
        JumlahNotifikasi::tanpaDitandai($query, (int) $admin->id, 'pengumpulan', 'submissions.id');

        return $query;
    }

    /*
    |--------------------------------------------------------------------------
    | Pengambilan data feed -> bentuk seragam untuk view
    |--------------------------------------------------------------------------
    */

    private function ambilMateri(User $admin, ?Carbon $sejak, string $q, int $batas): Collection
    {
        return $this->materiBase($admin, $sejak, $q)
            ->select(
                'materials.*',
                'courses.code as course_code',
                'courses.name as course_name',
                'pengunggah.name as actor_name',
                'pengunggah.role as actor_role'
            )
            ->orderByDesc('materials.created_at')
            ->limit($batas)
            ->get()
            ->map(function ($r) {
                $at = Carbon::parse($r->created_at);
                $berkas = $r->type === 'file';

                return $this->bentuk($at, [
                    'jenis' => 'materi',
                    'nada' => 'materi',
                    'id' => $r->id,
                    'course_id' => $r->course_id,
                    'course_code' => $r->course_code,
                    'course_name' => $r->course_name,
                    'actor' => $r->actor_name,
                    'peran' => ucfirst((string) $r->actor_role),
                    'aksi' => 'mengunggah materi',
                    'judul' => $r->title,
                    'chips' => [
                        ['Materi Baru', 'tone'],
                        [$berkas ? 'Berkas' : 'Tautan', 'muted'],
                    ],
                    'meta' => [],
                    'catatan' => $r->description ? Str::limit($r->description, 140) : null,
                    'lampiran' => $berkas
                        ? ['nama' => $r->original_name ?: 'Berkas materi', 'info' => FormatNotifikasi::ukuran((int) $r->file_size)]
                        : ['nama' => $r->external_url ?: 'Tautan materi', 'info' => 'Tautan eksternal'],
                ]);
            });
    }

    private function ambilTugas(User $admin, ?Carbon $sejak, string $q, int $batas): Collection
    {
        return $this->tugasBase($admin, $sejak, $q)
            ->select(
                'assignments.*',
                'courses.code as course_code',
                'courses.name as course_name',
                'pembuat.name as actor_name',
                'pembuat.role as actor_role'
            )
            ->orderByDesc('assignments.created_at')
            ->limit($batas)
            ->get()
            ->map(function ($r) {
                $at = Carbon::parse($r->created_at);
                $tenggat = Carbon::parse($r->due_at)->locale('id');

                return $this->bentuk($at, [
                    'jenis' => 'tugas',
                    'nada' => 'tugas',
                    'id' => $r->id,
                    'course_id' => $r->course_id,
                    'course_code' => $r->course_code,
                    'course_name' => $r->course_name,
                    'actor' => $r->actor_name,
                    'peran' => ucfirst((string) $r->actor_role),
                    'aksi' => 'menerbitkan tugas',
                    'judul' => $r->title,
                    'chips' => [
                        ['Tugas Baru', 'tone'],
                    ],
                    'meta' => [
                        'Tenggat ' . $tenggat->translatedFormat('j M Y, H:i'),
                        'Nilai maks. ' . $r->max_score,
                        $r->allow_late ? 'Boleh terlambat' : 'Tidak menerima terlambat',
                    ],
                    'catatan' => null,
                    'lampiran' => null,
                ]);
            });
    }

    private function ambilKumpul(User $admin, ?Carbon $sejak, string $q, int $batas, bool $hanyaTerlambat): Collection
    {
        $query = $this->kumpulBase($admin, $sejak, $q);

        if ($hanyaTerlambat) {
            $query->where('submissions.is_late', true);
        }

        return $query
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
            ->limit($batas)
            ->get()
            ->map(function ($r) {
                $at = Carbon::parse($r->submitted_at);
                $telat = (bool) $r->is_late;
                $versi = (int) $r->versi;

                $chips = [
                    $telat ? ['Terlambat', 'bad'] : ['Tepat Waktu', 'ok'],
                    $r->sudah_dinilai ? ['Sudah Dinilai', 'info'] : ['Menunggu Nilai', 'warn'],
                ];

                if ($versi > 1) {
                    array_unshift($chips, ['Revisi v' . $versi, 'tone']);
                }

                return $this->bentuk($at, [
                    'jenis' => 'pengumpulan',
                    'nada' => $telat ? 'terlambat' : 'pengumpulan',
                    'id' => $r->id,
                    'assignment_id' => $r->assignment_id,
                    'course_id' => $r->course_id,
                    'course_code' => $r->course_code,
                    'course_name' => $r->course_name,
                    'actor' => $r->actor_name,
                    'peran' => 'Mahasiswa',
                    'aksi' => 'mengumpulkan tugas',
                    'judul' => $r->assignment_title,
                    'chips' => $chips,
                    'meta' => [
                        'Batas ' . Carbon::parse($r->assignment_due)->locale('id')->translatedFormat('j M Y, H:i'),
                    ],
                    'catatan' => $r->note ? Str::limit($r->note, 140) : null,
                    'lampiran' => ['nama' => $r->original_name, 'info' => FormatNotifikasi::ukuran((int) $r->file_size)],
                ]);
            });
    }

    /** Lengkapi item dengan waktu yang sudah diformat. */
    private function bentuk(Carbon $at, array $data): array
    {
        $lokal = $at->copy()->locale('id');

        return $data + [
            'at' => $at,
            'jam' => $at->format('H:i'),
            'lengkap' => $lokal->translatedFormat('j F Y, H:i'),
            'relatif' => $lokal->diffForHumans(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Kartu ringkasan (selalu 7 hari terakhir, tidak ikut filter)
    |--------------------------------------------------------------------------
    */
    public function ringkasan(): array
    {
        $sejak = now()->subDays(6)->startOfDay();
        $hariIni = now()->startOfDay();

        // SATU query dengan 10 subquery scalar, bukan 10 query count() terpisah.
        $r = DB::query()
            ->selectSub(Material::where('created_at', '>=', $sejak)->selectRaw('count(*)'), 'materi')
            ->selectSub(Material::where('created_at', '>=', $sejak)->where('type', 'file')->selectRaw('count(*)'), 'materi_berkas')
            ->selectSub(Material::where('created_at', '>=', $hariIni)->selectRaw('count(*)'), 'materi_hari_ini')
            ->selectSub(Assignment::where('status', 'published')->where('created_at', '>=', $sejak)->selectRaw('count(*)'), 'tugas')
            ->selectSub(Assignment::where('status', 'published')->where('created_at', '>=', $hariIni)->selectRaw('count(*)'), 'tugas_hari_ini')
            ->selectSub(Assignment::where('status', 'draft')->selectRaw('count(*)'), 'tugas_draf')
            ->selectSub(Submission::where('submitted_at', '>=', $sejak)->selectRaw('count(*)'), 'kumpul')
            ->selectSub(Submission::where('submitted_at', '>=', $sejak)->where('is_late', true)->selectRaw('count(*)'), 'kumpul_telat')
            ->selectSub(Submission::where('submitted_at', '>=', $hariIni)->selectRaw('count(*)'), 'kumpul_hari_ini')
            ->selectSub(Submission::whereDoesntHave('grade')->selectRaw('count(*)'), 'menunggu_nilai')
            ->first();

        $materi = (int) $r->materi;
        $materiBerkas = (int) $r->materi_berkas;
        $materiHariIni = (int) $r->materi_hari_ini;
        $tugas = (int) $r->tugas;
        $tugasHariIni = (int) $r->tugas_hari_ini;
        $kumpul = (int) $r->kumpul;
        $kumpulTelat = (int) $r->kumpul_telat;
        $kumpulHariIni = (int) $r->kumpul_hari_ini;

        return [
            'materi' => $materi,
            'materiBerkas' => $materiBerkas,
            'materiTautan' => $materi - $materiBerkas,
            'materiHariIni' => $materiHariIni,
            'tugas' => $tugas,
            'tugasHariIni' => $tugasHariIni,
            'tugasDraf' => (int) $r->tugas_draf,
            'kumpul' => $kumpul,
            'kumpulTelat' => $kumpulTelat,
            'tepatWaktu' => $kumpul > 0 ? (int) round((($kumpul - $kumpulTelat) / $kumpul) * 100) : null,
            'menungguNilai' => (int) $r->menunggu_nilai,
            'aktivitasHariIni' => $materiHariIni + $tugasHariIni + $kumpulHariIni,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Mata kuliah dengan tingkat pengumpulan tertinggi
    |   persentase = pengumpulan / (tugas terbit x mahasiswa terdaftar)
    |--------------------------------------------------------------------------
    */
    public function mataKuliahAktif(): Collection
    {
        // SATU query: jumlah tugas terbit dan jumlah pengumpulan per mata kuliah
        // dihitung lewat subquery, bukan dua query terpisah lalu digabung di PHP.
        return Course::query()
            ->select(['courses.id', 'courses.code', 'courses.name'])
            ->withCount('students')
            ->addSelect([
                'tugas_terbit' => Assignment::query()
                    ->selectRaw('count(*)')
                    ->whereColumn('assignments.course_id', 'courses.id')
                    ->where('assignments.status', 'published'),
                'pengumpulan_total' => Submission::query()
                    ->selectRaw('count(*)')
                    ->join('assignments as a_kumpul', 'a_kumpul.id', '=', 'submissions.assignment_id')
                    ->whereColumn('a_kumpul.course_id', 'courses.id'),
            ])
            // Hanya mata kuliah yang punya minimal satu tugas terbit.
            ->whereExists(function ($w) {
                $w->selectRaw('1')
                    ->from('assignments as a_terbit')
                    ->whereColumn('a_terbit.course_id', 'courses.id')
                    ->where('a_terbit.status', 'published');
            })
            ->get()
            ->map(function ($c) {
                $tugasTerbit = (int) $c->tugas_terbit;
                $target = (int) $c->students_count * $tugasTerbit;
                $masuk = (int) $c->pengumpulan_total;
                $persen = $target > 0 ? min(100, (int) round(($masuk / $target) * 100)) : 0;

                return [
                    'id' => $c->id,
                    'kode' => $c->code,
                    'nama' => $c->name,
                    'persen' => $persen,
                    'masuk' => $masuk,
                    'tugas' => $tugasTerbit,
                    'mahasiswa' => (int) $c->students_count,
                    'level' => FormatNotifikasi::level($persen),
                ];
            })
            ->sortByDesc(fn (array $c) => [$c['persen'], $c['masuk']])
            ->take(5)
            ->values();
    }

    /*
    |--------------------------------------------------------------------------
    | Tugas terbit dengan batas waktu paling dekat
    |--------------------------------------------------------------------------
    */
    public function tenggatTerdekat(): Collection
    {
        return Assignment::query()
            ->where('assignments.status', 'published')
            ->where('assignments.due_at', '>=', now())
            ->join('courses', 'courses.id', '=', 'assignments.course_id')
            ->leftJoin('users as dosen', 'dosen.id', '=', 'courses.lecturer_id')
            ->select(
                'assignments.id',
                'assignments.title',
                'assignments.due_at',
                'courses.code as course_code',
                'dosen.name as dosen_name'
            )
            ->orderBy('assignments.due_at')
            ->limit(4)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'judul' => $r->title,
                'kode' => $r->course_code,
                'dosen' => $r->dosen_name ?: 'Belum ditentukan',
            ] + FormatNotifikasi::tenggat($r->due_at));
    }
}