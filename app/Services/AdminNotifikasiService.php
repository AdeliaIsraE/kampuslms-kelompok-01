<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\Material;
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
 * (materi diunggah dan tugas terbit) dibentuk langsung dari tabel materials
 * dan assignments, sehingga selalu sesuai data terbaru dan tidak perlu menulis
 * baris notifikasi untuk setiap event. Status dibaca disimpan di
 * users.notifications_read_at ("tandai semua") dan tabel notifikasi_admin_dibaca
 * ("tandai satu per satu").
 *
 * Lingkup sengaja dibatasi pada aktivitas administratif. Pengumpulan tugas
 * mahasiswa TIDAK dimasukkan: itu log operasional per kelas yang jumlahnya
 * besar (se-kampus), membuat angka lonceng selalu penuh sehingga sinyal penting
 * tenggelam, dan membebani database karena lonceng dihitung ulang tiap 30 detik.
 * Pengumpulan ditangani dosen pengampu lewat DosenNotifikasiService. Tidak ada
 * query ke tabel submissions di class ini.
 *
 * Controller hanya membaca request dan menampilkan view; seluruh query,
 * agregasi, dan pembentukan item ada di sini.
 */
class AdminNotifikasiService
{
    public const PER_PAGE = 8;

    public const TAB = [
        'semua' => 'Semua Aktivitas',
        'materi' => 'Materi',
        'tugas' => 'Tugas',
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

        // SATU query dengan 2 subquery count, bukan 2 query terpisah.
        $hitung = DB::query()
            ->selectSub($this->materiBase($admin, $sejak, $q)->selectRaw('count(*)'), 'n_materi')
            ->selectSub($this->tugasBase($admin, $sejak, $q)->selectRaw('count(*)'), 'n_tugas')
            ->first();

        $nMateri = (int) $hitung->n_materi;
        $nTugas = (int) $hitung->n_tugas;

        $jumlah = [
            'semua' => $nMateri + $nTugas,
            'materi' => $nMateri,
            'tugas' => $nTugas,
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

        if (in_array($tab, ['semua', 'materi'], true)) {
            $item = $item->merge($this->ambilMateri($admin, $sejak, $q, $ambil));
        }

        if (in_array($tab, ['semua', 'tugas'], true)) {
            $item = $item->merge($this->ambilTugas($admin, $sejak, $q, $ambil));
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
     * @param  string  $jenis  'materi' | 'tugas'
     *
     * @throws ModelNotFoundException bila jenis tidak dikenal atau aktivitasnya tidak ada (jadi 404)
     */
    public function tandaiDibaca(User $admin, string $jenis, int $id): void
    {
        $sumber = match ($jenis) {
            'materi' => Material::query(),
            'tugas' => Assignment::query()->where('status', 'published'),
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

        // SATU query dengan 6 subquery scalar, bukan 6 query count() terpisah.
        $r = DB::query()
            ->selectSub(Material::where('created_at', '>=', $sejak)->selectRaw('count(*)'), 'materi')
            ->selectSub(Material::where('created_at', '>=', $sejak)->where('type', 'file')->selectRaw('count(*)'), 'materi_berkas')
            ->selectSub(Material::where('created_at', '>=', $hariIni)->selectRaw('count(*)'), 'materi_hari_ini')
            ->selectSub(Assignment::where('status', 'published')->where('created_at', '>=', $sejak)->selectRaw('count(*)'), 'tugas')
            ->selectSub(Assignment::where('status', 'published')->where('created_at', '>=', $hariIni)->selectRaw('count(*)'), 'tugas_hari_ini')
            ->selectSub(Assignment::where('status', 'draft')->selectRaw('count(*)'), 'tugas_draf')
            ->first();

        $materi = (int) $r->materi;
        $materiBerkas = (int) $r->materi_berkas;
        $materiHariIni = (int) $r->materi_hari_ini;
        $tugas = (int) $r->tugas;
        $tugasHariIni = (int) $r->tugas_hari_ini;

        return [
            'materi' => $materi,
            'materiBerkas' => $materiBerkas,
            'materiTautan' => $materi - $materiBerkas,
            'materiHariIni' => $materiHariIni,
            'tugas' => $tugas,
            'tugasHariIni' => $tugasHariIni,
            'tugasDraf' => (int) $r->tugas_draf,
            'aktivitasHariIni' => $materiHariIni + $tugasHariIni,
        ];
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