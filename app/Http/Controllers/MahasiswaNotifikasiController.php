<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Pusat Notifikasi mahasiswa.
 *
 * Berbeda dengan halaman admin dan dosen (feed yang diturunkan dari tabel lain),
 * daftar utama di sini adalah notifikasi database milik mahasiswa yang login
 * (tabel notifications), lengkap dengan status belum/sudah dibaca. Kartu ringkasan
 * dan kolom samping tetap diturunkan dari data mata kuliah yang diikuti.
 *
 * Bentuk payload (kolom data) yang diharapkan dari Notification di Build nomor 1,
 * sesuai aturan nomor 8 (hanya ID dan judul, tanpa nilai atau feedback):
 *
 *   ['jenis' => 'tugas_baru' | 'nilai', 'assignment_id' => int, 'judul' => string]
 *
 * Kepemilikan: semua query berangkat dari $request->user()->notifications(), jadi
 * notifikasi milik mahasiswa lain tidak pernah terbaca atau bisa ditandai (404).
 */
class MahasiswaNotifikasiController extends Controller
{
    private const PER_PAGE = 8;

    private const TAB = [
        'semua' => 'Semua',
        'belum' => 'Belum Dibaca',
        'tugas' => 'Tugas Baru',
        'nilai' => 'Nilai',
    ];

    private const PERIODE = [
        'hari-ini' => 'Hari ini',
        '7-hari' => '7 hari terakhir',
        '30-hari' => '30 hari terakhir',
        'semua' => 'Semua waktu',
    ];

    public function index(Request $request): View
    {
        $user = $request->user();

        /* Filter dari URL (nilai di luar daftar diabaikan) */
        $tab = $request->query('tab');
        $tab = is_string($tab) && isset(self::TAB[$tab]) ? $tab : 'semua';

        $periode = $request->query('periode');
        $periode = is_string($periode) && isset(self::PERIODE[$periode]) ? $periode : 'semua';

        $sejak = match ($periode) {
            'hari-ini' => now()->startOfDay(),
            '7-hari' => now()->subDays(6)->startOfDay(),
            '30-hari' => now()->subDays(29)->startOfDay(),
            default => null,
        };

        /* Jumlah per tab (mengikuti periode): SATU query dengan 4 subquery count */
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

        /* Daftar halaman ini. Total sudah diketahui dari hitungan tab di atas,
           jadi tidak perlu query COUNT kedua seperti yang dilakukan paginate(). */
        $total = $tabs[$tab]['jumlah'];
        $halamanAktif = LengthAwarePaginator::resolveCurrentPage();

        $notifikasi = $this->terapkanTab($this->dasar($user, $sejak), $tab)
            ->forPage($halamanAktif, self::PER_PAGE)
            ->get();

        $tugas = $this->infoTugas($notifikasi);
        $item = $notifikasi->map(fn ($n) => $this->bentukItem($n, $tugas));

        $halaman = (new LengthAwarePaginator($item, $total, self::PER_PAGE, $halamanAktif, [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
            'pageName' => 'page',
        ]))->withQueryString();

        $grupHari = $halaman->getCollection()
            ->groupBy(fn (array $i) => $i['at']->toDateString())
            ->map(fn (Collection $isi) => [
                'label' => $this->labelHari($isi->first()['at']),
                'jumlah' => $isi->count(),
                'items' => $isi->all(),
            ])
            ->values();

        return view('mahasiswa.notifikasi', [
            'ringkasan' => $this->ringkasan($user),
            'tenggat' => $this->tenggatTerdekat($user),
            'progres' => $this->progresPengumpulan($user),
            'tab' => $tab,
            'tabs' => $tabs,
            'periode' => $periode,
            'opsiPeriode' => self::PERIODE,
            'grupHari' => $grupHari,
            'halaman' => $halaman,
            'total' => $halaman->total(),
        ]);
    }

    /**
     * POST mahasiswa/notifikasi/{id}/dibaca
     *
     * Hanya notifikasi milik mahasiswa yang login yang bisa ditandai; id milik
     * orang lain (atau tidak ada) menghasilkan 404.
     */
    public function dibaca(Request $request, string $id): RedirectResponse
    {
        $notifikasi = $request->user()
            ->notifications()
            ->whereKey($id)
            ->firstOrFail();

        $notifikasi->markAsRead();

        // back() mempertahankan tab, periode, dan halaman yang sedang dibuka.
        return back();
    }

    /** POST mahasiswa/notifikasi/dibaca-semua */
    public function dibacaSemua(Request $request): RedirectResponse
    {
        $jumlah = $request->user()
            ->unreadNotifications()
            ->update(['read_at' => now()]);

        return back()->with(
            'success',
            $jumlah > 0 ? "{$jumlah} notifikasi ditandai sudah dibaca." : 'Tidak ada notifikasi yang belum dibaca.'
        );
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
    private function ringkasan(User $user): array
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
    private function tenggatTerdekat(User $user): Collection
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
            ->map(function ($r) {
                $due = Carbon::parse($r->due_at)->locale('id');
                $detik = max(0, $due->getTimestamp() - now()->getTimestamp());
                $hari = intdiv($detik, 86400);
                $jam = intdiv($detik % 86400, 3600);
                $menit = intdiv($detik % 3600, 60);

                return [
                    'id' => $r->id,
                    'judul' => $r->title,
                    'kode' => $r->course_code,
                    'nama' => $r->course_name,
                    'tanggal' => $due->translatedFormat('j M'),
                    'jam' => $due->format('H:i'),
                    'sisa' => $hari > 0
                        ? "Sisa {$hari} hari {$jam} jam"
                        : "Sisa {$jam} jam {$menit} menit",
                    'mendesak' => $detik < 86400,
                ];
            });
    }

    /*
    |--------------------------------------------------------------------------
    | Progres pengumpulan per mata kuliah (yang paling banyak belum dikumpulkan di atas)
    |--------------------------------------------------------------------------
    */
    private function progresPengumpulan(User $user): Collection
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
                    'level' => $persen >= 75 ? 'tinggi' : ($persen >= 50 ? 'sedang' : 'rendah'),
                ];
            })
            ->filter(fn (array $c) => $c['total'] > 0)
            ->sortByDesc('belum')
            ->take(4)
            ->values();
    }

    private function labelHari(Carbon $tgl): string
    {
        $lokal = $tgl->copy()->locale('id');
        $tanggal = $lokal->translatedFormat('j F Y');

        return match (true) {
            $tgl->isToday() => "Hari Ini — {$tanggal}",
            $tgl->isYesterday() => "Kemarin — {$tanggal}",
            default => $lokal->translatedFormat('l') . ", {$tanggal}",
        };
    }
}