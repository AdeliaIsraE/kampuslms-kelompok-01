<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\Submission;
use App\Models\User;
use App\Support\FormatNotifikasi;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Logika data Pusat Notifikasi mahasiswa.
 *
 * Berbeda dengan admin dan dosen (feed yang diturunkan dari tabel lain), daftar
 * utama di sini adalah notifikasi database bawaan Laravel milik mahasiswa yang
 * login ($user->notifications(), tabel notifications), lengkap dengan status
 * belum/sudah dibaca. Kartu ringkasan dan kolom samping diturunkan dari data
 * mata kuliah yang diikuti.
 *
 * Bentuk payload (kolom data) yang diharapkan dari Notification, sesuai aturan
 * nomor 8 (hanya ID dan judul, tanpa nilai atau feedback):
 *
 *   ['jenis' => 'tugas_baru' | 'nilai', 'assignment_id' => int, 'judul' => string]
 *
 * Kepemilikan: semua query berangkat dari $user->notifications(), jadi
 * notifikasi milik mahasiswa lain tidak pernah terbaca atau bisa ditandai (404).
 */
class MahasiswaNotifikasiService
{
    public const PER_PAGE = 8;

    public const TAB = [
        'semua' => 'Semua',
        'belum' => 'Belum Dibaca',
        'tugas' => 'Tugas Baru',
        'nilai' => 'Nilai',
    ];

    /*
    |--------------------------------------------------------------------------
    | Daftar notifikasi + hitungan tab
    |--------------------------------------------------------------------------
    */

    /**
     * @return array{tabs: array, total: int, items: Collection}
     */
    public function feed(User $user, string $tab, string $periode, int $halaman): array
    {
        $sejak = FormatNotifikasi::sejak($periode);

        // Jumlah per tab (mengikuti periode): SATU query dengan 4 subquery count.
        $hitung = DB::query();
        foreach (array_keys(self::TAB) as $kunci) {
            $hitung->selectSub(
                $this->hitungSub($this->terapkanTab($this->dasar($user, $sejak), $kunci)),
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
        $notifikasi = $this->terapkanTab($this->dasar($user, $sejak), $tab)
            ->forPage($halaman, self::PER_PAGE)
            ->get();

        $tugas = $this->infoTugas($notifikasi);

        return [
            'tabs' => $tabs,
            'total' => $tabs[$tab]['jumlah'],
            'items' => $notifikasi->map(fn ($n) => $this->bentukItem($n, $tugas)),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Aksi tandai dibaca (memakai API notifikasi bawaan Laravel)
    |--------------------------------------------------------------------------
    */

    /**
     * Hanya notifikasi milik mahasiswa ini yang bisa ditandai.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException bila milik orang lain atau tidak ada (jadi 404)
     */
    public function tandaiDibaca(User $user, string $id): void
    {
        $user->notifications()
            ->whereKey($id)
            ->firstOrFail()
            ->markAsRead();
    }

    /** @return int jumlah notifikasi yang ditandai */
    public function tandaiSemuaDibaca(User $user): int
    {
        return $user->unreadNotifications()->update(['read_at' => now()]);
    }

    /*
    |--------------------------------------------------------------------------
    | Query dasar: notifikasi milik mahasiswa ini
    |--------------------------------------------------------------------------
    */
    private function dasar(User $user, ?Carbon $sejak)
    {
        $relasi = $user->notifications();

        if ($sejak) {
            $relasi->where('created_at', '>=', $sejak);
        }

        return $relasi;
    }

    private function terapkanTab($relasi, string $tab)
    {
        return match ($tab) {
            'belum' => $relasi->whereNull('read_at'),
            'tugas' => $relasi->where('data->jenis', 'tugas_baru'),
            'nilai' => $relasi->where('data->jenis', 'nilai'),
            default => $relasi,
        };
    }

    /** Info tugas + mata kuliah untuk notifikasi di halaman ini (satu query, dikunci per id tugas). */
    private function infoTugas(Collection $notifikasi): Collection
    {
        $ids = $notifikasi
            ->map(fn ($n) => (int) (((array) $n->data)['assignment_id'] ?? 0))
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return Assignment::query()
            ->join('courses', 'courses.id', '=', 'assignments.course_id')
            ->whereIn('assignments.id', $ids)
            ->get([
                'assignments.id',
                'assignments.due_at',
                'courses.id as course_id',
                'courses.code as course_code',
                'courses.name as course_name',
            ])
            ->keyBy('id');
    }

    /** Satu notifikasi -> bentuk seragam untuk view. */
    private function bentukItem($n, Collection $tugas): array
    {
        $data = (array) $n->data;
        $jenis = $data['jenis'] ?? 'lain';
        $info = $tugas->get((int) ($data['assignment_id'] ?? 0));
        $at = Carbon::parse($n->created_at);
        $lokal = $at->copy()->locale('id');

        return [
            'id' => $n->id,
            'jenis' => $jenis,
            'nada' => $jenis === 'nilai' ? 'pengumpulan' : 'tugas',
            'dibaca' => $n->read_at !== null,
            'judul' => (string) ($data['judul'] ?? 'Tanpa judul'),
            // null bila tugasnya sudah dihapus
            'assignment_id' => $info?->id,
            'course_id' => $info?->course_id,
            'course_code' => $info?->course_code,
            'course_name' => $info?->course_name,
            'batas' => $info && $info->due_at
                ? 'Batas ' . Carbon::parse($info->due_at)->locale('id')->translatedFormat('j M Y, H:i')
                : null,
            'at' => $at,
            'lengkap' => $lokal->translatedFormat('j F Y, H:i'),
            'relatif' => $lokal->diffForHumans(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Kartu ringkasan (tidak ikut filter)
    |--------------------------------------------------------------------------
    */
    public function ringkasan(User $user): array
    {
        // SATU query dengan 5 subquery count, bukan 5 query terpisah.
        $r = DB::query()
            ->selectSub($this->hitungSub($user->unreadNotifications()), 'belum')
            ->selectSub($this->hitungSub($user->notifications()), 'total')
            ->selectSub($this->hitungSub($user->notifications()->where('created_at', '>=', now()->startOfDay())), 'hari_ini')
            ->selectSub($this->belumDikumpulkan($user)->selectRaw('count(*)'), 'perlu_kumpul')
            ->selectSub(Submission::where('user_id', $user->id)->whereHas('grade')->selectRaw('count(*)'), 'nilai')
            ->first();

        return [
            'belum' => (int) $r->belum,
            'total' => (int) $r->total,
            'hariIni' => (int) $r->hari_ini,
            'perluKumpul' => (int) $r->perlu_kumpul,
            'nilai' => (int) $r->nilai,
        ];
    }

    /**
     * Ubah relasi notifikasi menjadi subquery COUNT. Urutan bawaan relasi
     * (latest) dibuang agar aman untuk query agregat di MySQL strict.
     */
    private function hitungSub($relasi)
    {
        return $relasi->getQuery()->reorder()->selectRaw('count(*)');
    }

    /** Subquery id mata kuliah yang diikuti mahasiswa ini (dipakai di whereIn, tanpa query terpisah). */
    private function mkDiikuti(User $user)
    {
        return DB::table('course_user')->select('course_id')->where('user_id', $user->id);
    }

    /** Tugas terbit di mata kuliah yang diikuti, belum lewat batas waktu, dan belum dikumpulkan mahasiswa ini. */
    private function belumDikumpulkan(User $user)
    {
        return Assignment::query()
            ->join('courses', 'courses.id', '=', 'assignments.course_id')
            ->whereIn('assignments.course_id', $this->mkDiikuti($user))
            ->where('assignments.status', 'published')
            ->where('assignments.due_at', '>=', now())
            ->whereNotExists(function ($sub) use ($user) {
                $sub->selectRaw('1')
                    ->from('submissions')
                    ->whereColumn('submissions.assignment_id', 'assignments.id')
                    ->where('submissions.user_id', $user->id);
            });
    }

    /*
    |--------------------------------------------------------------------------
    | Batas waktu terdekat (kolom samping)
    |--------------------------------------------------------------------------
    */
    public function tenggatTerdekat(User $user): Collection
    {
        return $this->belumDikumpulkan($user)
            ->select(
                'assignments.id',
                'assignments.title',
                'assignments.due_at',
                'courses.code as course_code',
                'courses.name as course_name'
            )
            ->orderBy('assignments.due_at')
            ->limit(4)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'judul' => $r->title,
                'kode' => $r->course_code,
                'nama' => $r->course_name,
            ] + FormatNotifikasi::tenggat($r->due_at));
    }

    /*
    |--------------------------------------------------------------------------
    | Progres pengumpulan per mata kuliah (yang paling banyak belum dikumpulkan di atas)
    |--------------------------------------------------------------------------
    */
    public function progresPengumpulan(User $user): Collection
    {
        // SATU query: total tugas terbit dan jumlah yang sudah dikumpulkan mahasiswa ini
        // dihitung lewat subquery per mata kuliah, bukan tiga query terpisah.
        return Course::query()
            ->select(['courses.id', 'courses.code', 'courses.name'])
            ->whereIn('courses.id', $this->mkDiikuti($user))
            ->addSelect([
                'total_tugas' => Assignment::query()
                    ->selectRaw('count(*)')
                    ->whereColumn('assignments.course_id', 'courses.id')
                    ->where('assignments.status', 'published'),
                'total_kumpul' => Submission::query()
                    ->selectRaw('count(distinct submissions.assignment_id)')
                    ->join('assignments as a_kumpul', 'a_kumpul.id', '=', 'submissions.assignment_id')
                    ->where('submissions.user_id', $user->id)
                    ->where('a_kumpul.status', 'published')
                    ->whereColumn('a_kumpul.course_id', 'courses.id'),
            ])
            ->orderBy('courses.name')
            ->get()
            ->map(function ($c) {
                $total = (int) $c->total_tugas;
                $kumpul = min($total, (int) $c->total_kumpul);
                $persen = $total > 0 ? (int) round(($kumpul / $total) * 100) : 0;

                return [
                    'id' => $c->id,
                    'kode' => $c->code,
                    'nama' => $c->name,
                    'total' => $total,
                    'kumpul' => $kumpul,
                    'belum' => $total - $kumpul,
                    'persen' => $persen,
                    'level' => FormatNotifikasi::level($persen),
                ];
            })
            ->filter(fn (array $c) => $c['total'] > 0)
            ->sortByDesc('belum')
            ->take(4)
            ->values();
    }
}