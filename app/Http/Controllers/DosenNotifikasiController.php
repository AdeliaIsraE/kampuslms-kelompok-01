<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\Submission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Pusat Notifikasi dosen.
 *
 * Hanya menampilkan pengumpulan tugas pada mata kuliah yang diampu dosen
 * yang sedang login (courses.lecturer_id). Data diturunkan dari tabel yang
 * sudah ada, tanpa tabel notifikasi dan tanpa status dibaca.
 */
class DosenNotifikasiController extends Controller
{
    private const PER_PAGE = 8;

    private const TAB = [
        'semua' => 'Semua Pengumpulan',
        'menunggu' => 'Menunggu Penilaian',
        'terlambat' => 'Terlambat',
        'dinilai' => 'Sudah Dinilai',
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
        | Mata kuliah milik dosen ini (batas semua data di halaman)
        |--------------------------------------------------------------------------
        */
        $mataKuliah = Course::where('lecturer_id', auth()->id())
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $mkIds = $mataKuliah->pluck('id')->map(fn ($id) => (int) $id)->all();

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

        $mk = $request->query('mk');
        $mk = is_string($mk) && ctype_digit($mk) && in_array((int) $mk, $mkIds, true) ? (int) $mk : null;

        $sejak = match ($periode) {
            'hari-ini' => now()->startOfDay(),
            '7-hari' => now()->subDays(6)->startOfDay(),
            '30-hari' => now()->subDays(29)->startOfDay(),
            default => null,
        };

        /*
        |--------------------------------------------------------------------------
        | Jumlah per tab (mengikuti pencarian, mata kuliah, dan periode)
        |--------------------------------------------------------------------------
        */
        // SATU query dengan 4 subquery count, bukan 4 query terpisah.
        $hitung = DB::query();
        foreach (array_keys(self::TAB) as $kunci) {
            $hitung->selectSub(
                $this->terapkanTab($this->kumpulBase($mkIds, $sejak, $q, $mk), $kunci)->selectRaw('count(*)'),
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

        /*
        |--------------------------------------------------------------------------
        | Feed halaman ini
        |--------------------------------------------------------------------------
        */
        // Total sudah diketahui dari hitungan tab di atas, jadi tidak perlu
        // query COUNT kedua seperti yang dilakukan paginate().
        $total = $tabs[$tab]['jumlah'];
        $halamanAktif = LengthAwarePaginator::resolveCurrentPage();

        $baris = $this->terapkanTab($this->kumpulBase($mkIds, $sejak, $q, $mk), $tab)
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
            ->forPage($halamanAktif, self::PER_PAGE)
            ->get()
            ->map(fn ($r) => $this->bentukItem($r));

        $halaman = (new LengthAwarePaginator($baris, $total, self::PER_PAGE, $halamanAktif, [
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

        return view('dosen.notifikasi', [
            'ringkasan' => $this->ringkasan($mkIds),
            'antrean' => $this->antreanPenilaian($mataKuliah, $mkIds),
            'tenggat' => $this->tenggatTerdekat($mkIds),
            'mataKuliah' => $mataKuliah,
            'tab' => $tab,
            'tabs' => $tabs,
            'periode' => $periode,
            'opsiPeriode' => self::PERIODE,
            'q' => $q,
            'mk' => $mk,
            'grupHari' => $grupHari,
            'halaman' => $halaman,
            'total' => $halaman->total(),
        ]);
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
            'lampiran' => ['nama' => $r->original_name, 'info' => $this->ukuran((int) $r->file_size)],
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
    private function ringkasan(array $mkIds): array
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
    private function antreanPenilaian(Collection $mataKuliah, array $mkIds): Collection
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
                    'level' => $persen >= 75 ? 'tinggi' : ($persen >= 50 ? 'sedang' : 'rendah'),
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
    private function tenggatTerdekat(array $mkIds): Collection
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
                    'terkumpul' => (int) $r->terkumpul,
                    'mahasiswa' => (int) $r->jumlah_mahasiswa,
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