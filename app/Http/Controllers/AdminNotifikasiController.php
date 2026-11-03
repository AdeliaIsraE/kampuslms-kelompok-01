<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\Material;
use App\Models\Submission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Pusat Notifikasi admin.
 *
 * Tidak memakai tabel notifikasi: feed dibentuk langsung dari tabel yang
 * sudah ada (materials, assignments, submissions), sehingga selalu sesuai
 * dengan data terbaru dan tidak perlu migration baru.
 */
class AdminNotifikasiController extends Controller
{
    private const PER_PAGE = 8;

    private const TAB = [
        'semua' => 'Semua Aktivitas',
        'materi-tugas' => 'Materi & Tugas',
        'pengumpulan' => 'Pengumpulan',
        'terlambat' => 'Terlambat',
    ];

    private const PERIODE = [
        'hari-ini' => 'Hari ini',
        '7-hari' => '7 hari terakhir',
        '30-hari' => '30 hari terakhir',
        'semua' => 'Semua waktu',
    ];

    public function index(Request $request): View
    {
        /*
        |--------------------------------------------------------------------------
        | Filter dari URL (nilai di luar daftar diabaikan)
        |--------------------------------------------------------------------------
        */
        $tab = $request->query('tab');
        $tab = is_string($tab) && isset(self::TAB[$tab]) ? $tab : 'semua';

        $periode = $request->query('periode');
        $periode = is_string($periode) && isset(self::PERIODE[$periode]) ? $periode : '30-hari';

        $q = $request->query('q');
        $q = is_string($q) ? mb_substr(trim($q), 0, 100) : '';

        $sejak = match ($periode) {
            'hari-ini' => now()->startOfDay(),
            '7-hari' => now()->subDays(6)->startOfDay(),
            '30-hari' => now()->subDays(29)->startOfDay(),
            default => null,
        };

        /*
        |--------------------------------------------------------------------------
        | Jumlah per jenis (dipakai tab dan pagination)
        |--------------------------------------------------------------------------
        */
        $nMateri = $this->materiBase($sejak, $q)->count();
        $nTugas = $this->tugasBase($sejak, $q)->count();
        $nKumpul = $this->kumpulBase($sejak, $q)->count();
        $nTerlambat = $this->kumpulBase($sejak, $q)->where('submissions.is_late', true)->count();

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

        /*
        |--------------------------------------------------------------------------
        | Feed: ambil secukupnya dari tiap sumber, gabung, urutkan, potong
        |--------------------------------------------------------------------------
        */
        $total = $jumlah[$tab];
        $halamanTerakhir = max(1, (int) ceil($total / self::PER_PAGE));
        $halamanAktif = min($halamanTerakhir, max(1, (int) $request->query('page', 1)));
        $ambil = $halamanAktif * self::PER_PAGE;

        $item = collect();

        if (in_array($tab, ['semua', 'materi-tugas'], true)) {
            $item = $item->merge($this->ambilMateri($sejak, $q, $ambil));
            $item = $item->merge($this->ambilTugas($sejak, $q, $ambil));
        }

        if (in_array($tab, ['semua', 'pengumpulan', 'terlambat'], true)) {
            $item = $item->merge($this->ambilKumpul($sejak, $q, $ambil, $tab === 'terlambat'));
        }

        $halamanIni = $item
            ->sortByDesc(fn (array $i) => $i['at']->getTimestamp())
            ->values()
            ->slice(($halamanAktif - 1) * self::PER_PAGE, self::PER_PAGE)
            ->values();

        $halaman = new LengthAwarePaginator(
            $halamanIni,
            $total,
            self::PER_PAGE,
            $halamanAktif,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $grupHari = $halamanIni
            ->groupBy(fn (array $i) => $i['at']->toDateString())
            ->map(fn (Collection $isi) => [
                'label' => $this->labelHari($isi->first()['at']),
                'jumlah' => $isi->count(),
                'items' => $isi->all(),
            ])
            ->values();

        return view('admin.notifikasi', [
            'ringkasan' => $this->ringkasan(),
            'mkAktif' => $this->mataKuliahAktif(),
            'tenggat' => $this->tenggatTerdekat(),
            'tab' => $tab,
            'tabs' => $tabs,
            'periode' => $periode,
            'opsiPeriode' => self::PERIODE,
            'q' => $q,
            'grupHari' => $grupHari,
            'halaman' => $halaman,
            'total' => $total,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Query dasar (join + filter), dipakai untuk menghitung dan mengambil data
    |--------------------------------------------------------------------------
    */

    private function materiBase(?Carbon $sejak, string $q): Builder
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

        return $query;
    }

    private function tugasBase(?Carbon $sejak, string $q): Builder
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

        return $query;
    }

    private function kumpulBase(?Carbon $sejak, string $q): Builder
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

        return $query;
    }

    /*
    |--------------------------------------------------------------------------
    | Pengambilan data feed -> bentuk seragam untuk view
    |--------------------------------------------------------------------------
    */

    private function ambilMateri(?Carbon $sejak, string $q, int $batas): Collection
    {
        return $this->materiBase($sejak, $q)
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
                        ? ['nama' => $r->original_name ?: 'Berkas materi', 'info' => $this->ukuran((int) $r->file_size)]
                        : ['nama' => $r->external_url ?: 'Tautan materi', 'info' => 'Tautan eksternal'],
                ]);
            });
    }

    private function ambilTugas(?Carbon $sejak, string $q, int $batas): Collection
    {
        return $this->tugasBase($sejak, $q)
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

    private function ambilKumpul(?Carbon $sejak, string $q, int $batas, bool $hanyaTerlambat): Collection
    {
        $query = $this->kumpulBase($sejak, $q);

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
                    'lampiran' => ['nama' => $r->original_name, 'info' => $this->ukuran((int) $r->file_size)],
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
    private function ringkasan(): array
    {
        $sejak = now()->subDays(6)->startOfDay();
        $hariIni = now()->startOfDay();

        $materi = Material::where('created_at', '>=', $sejak)->count();
        $materiBerkas = Material::where('created_at', '>=', $sejak)->where('type', 'file')->count();
        $materiHariIni = Material::where('created_at', '>=', $hariIni)->count();

        $tugas = Assignment::where('status', 'published')->where('created_at', '>=', $sejak)->count();
        $tugasHariIni = Assignment::where('status', 'published')->where('created_at', '>=', $hariIni)->count();
        $tugasDraf = Assignment::where('status', 'draft')->count();

        $kumpul = Submission::where('submitted_at', '>=', $sejak)->count();
        $kumpulTelat = Submission::where('submitted_at', '>=', $sejak)->where('is_late', true)->count();
        $kumpulHariIni = Submission::where('submitted_at', '>=', $hariIni)->count();

        return [
            'materi' => $materi,
            'materiBerkas' => $materiBerkas,
            'materiTautan' => $materi - $materiBerkas,
            'materiHariIni' => $materiHariIni,
            'tugas' => $tugas,
            'tugasHariIni' => $tugasHariIni,
            'tugasDraf' => $tugasDraf,
            'kumpul' => $kumpul,
            'kumpulTelat' => $kumpulTelat,
            'tepatWaktu' => $kumpul > 0 ? (int) round((($kumpul - $kumpulTelat) / $kumpul) * 100) : null,
            'menungguNilai' => Submission::whereDoesntHave('grade')->count(),
            'aktivitasHariIni' => $materiHariIni + $tugasHariIni + $kumpulHariIni,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Mata kuliah dengan tingkat pengumpulan tertinggi
    |   persentase = pengumpulan / (tugas terbit x mahasiswa terdaftar)
    |--------------------------------------------------------------------------
    */
    private function mataKuliahAktif(): Collection
    {
        $tugasTerbit = Assignment::where('status', 'published')
            ->selectRaw('course_id, count(*) as total')
            ->groupBy('course_id')
            ->pluck('total', 'course_id');

        $pengumpulan = Submission::query()
            ->join('assignments', 'assignments.id', '=', 'submissions.assignment_id')
            ->selectRaw('assignments.course_id as course_id, count(*) as total')
            ->groupBy('assignments.course_id')
            ->pluck('total', 'course_id');

        return Course::query()
    ->select(['id', 'code', 'name'])
    ->withCount('students')
    ->whereIn('id', $tugasTerbit->keys())
    ->get()
            ->map(function ($c) use ($tugasTerbit, $pengumpulan) {
                $target = (int) $c->students_count * (int) $tugasTerbit[$c->id];
                $masuk = (int) ($pengumpulan[$c->id] ?? 0);
                $persen = $target > 0 ? min(100, (int) round(($masuk / $target) * 100)) : 0;

                return [
                    'id' => $c->id,
                    'kode' => $c->code,
                    'nama' => $c->name,
                    'persen' => $persen,
                    'masuk' => $masuk,
                    'tugas' => (int) $tugasTerbit[$c->id],
                    'mahasiswa' => (int) $c->students_count,
                    'level' => $persen >= 75 ? 'tinggi' : ($persen >= 50 ? 'sedang' : 'rendah'),
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
    private function tenggatTerdekat(): Collection
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
                    'dosen' => $r->dosen_name ?: 'Belum ditentukan',
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
    | Pembantu format
    |--------------------------------------------------------------------------
    */
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

    private function ukuran(int $byte): string
    {
        if ($byte >= 1048576) {
            return number_format($byte / 1048576, 1, ',', '.') . ' MB';
        }

        if ($byte >= 1024) {
            return number_format($byte / 1024, 0, ',', '.') . ' KB';
        }

        return $byte . ' B';
    }
}
