<?php

namespace App\Http\Controllers;

use App\Models\Course;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MahasiswaNilaiController extends Controller
{
    /**
     * Skala konversi nilai angka (0-100) ke huruf dan bobot mutu.
     * Urutan dari batas tertinggi; ubah di sini bila skala kampus berbeda.
     * [batas minimum, huruf, bobot mutu]
     */
    private const SKALA = [
        [85, 'A',  4.00],
        [80, 'A-', 3.75],
        [75, 'B+', 3.50],
        [70, 'B',  3.00],
        [65, 'B-', 2.75],
        [60, 'C+', 2.50],
        [55, 'C',  2.00],
        [40, 'D',  1.00],
        [0,  'E',  0.00],
    ];

    /** Nilai angka minimum untuk dinyatakan lulus (setara huruf C). */
    private const BATAS_LULUS = 55;

    private const STATUS = [
        'lulus' => 'Lulus',
        'perbaikan' => 'Perlu Perbaikan',
        'belum' => 'Belum Ada Nilai',
    ];

    /**
     * GET /mahasiswa/nilai
     * Rekap nilai per mata kuliah yang diikuti mahasiswa. Nilai mata kuliah =
     * rata-rata (score / max_score) tugas yang SUDAH dinilai, jadi bersifat
     * sementara selama masih ada tugas yang belum dinilai.
     */
    public function index(Request $request)
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:' . implode(',', array_keys(self::STATUS))],
        ]);

        $q = trim($data['q'] ?? '');
        $status = $data['status'] ?? null;
        $user = $request->user();

        $courses = Course::query()
            ->visibleTo($user)
            ->with('lecturer:id,name')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'sks', 'lecturer_id']);

        $tugas = $this->tugasMahasiswa($user->id, $courses->pluck('id'))->groupBy('course_id');

        $semua = $courses->map(fn (Course $c) => $this->rekapMataKuliah($c, $tugas->get($c->id, collect())))->values();

        $daftar = $semua
            ->when($q !== '', fn (Collection $k) => $k->filter(function (array $m) use ($q) {
                return mb_stripos($m['kode'], $q) !== false
                    || mb_stripos($m['nama'], $q) !== false
                    || mb_stripos($m['dosen'], $q) !== false;
            }))
            ->when($status, fn (Collection $k) => $k->where('status', $status))
            ->values();

        return view('mahasiswa.nilai', [
            'ringkasan' => $this->ringkasan($semua),
            'daftar' => $daftar,
            'q' => $q,
            'status' => $status,
            'opsiStatus' => self::STATUS,
            'jumlahSemua' => $semua->count(),
        ]);
    }

    /**
     * Semua tugas terbit di MK mahasiswa, lengkap dengan pengumpulan dan nilainya
     * (LEFT JOIN, jadi tugas yang belum dikumpulkan tetap muncul).
     */
    private function tugasMahasiswa(int $userId, Collection $courseIds): Collection
    {
        if ($courseIds->isEmpty()) {
            return collect();
        }

        return DB::table('assignments as a')
            ->leftJoin('submissions as s', function ($join) use ($userId) {
                $join->on('s.assignment_id', '=', 'a.id')->where('s.user_id', '=', $userId);
            })
            ->leftJoin('grades as g', function ($join) {
                // Hanya nilai yang sudah diterbitkan dosen yang boleh terlihat mahasiswa.
                $join->on('g.submission_id', '=', 's.id')->where('g.is_published', '=', true);
            })
            ->whereIn('a.course_id', $courseIds)
            ->where('a.status', 'published')
            ->orderBy('a.due_at')
            ->get([
                'a.id', 'a.course_id', 'a.title', 'a.due_at', 'a.max_score',
                's.id as submission_id', 's.is_late', 's.submitted_at',
                'g.score', 'g.feedback', 'g.graded_at',
            ]);
    }

    private function rekapMataKuliah(Course $c, Collection $tugas): array
    {
        $rincian = [];
        $persen = [];
        $dikumpul = 0;

        foreach ($tugas as $t) {
            $max = max(1, (int) $t->max_score);
            $punyaNilai = $t->score !== null;
            $sudahKumpul = $t->submission_id !== null;
            $batas = Carbon::parse($t->due_at);

            if ($sudahKumpul) {
                $dikumpul++;
            }

            if ($punyaNilai) {
                $persen[] = ((float) $t->score / $max) * 100;
                $kondisi = 'dinilai';
            } elseif ($sudahKumpul) {
                $kondisi = 'menunggu';
            } else {
                $kondisi = $batas->isPast() ? 'terlewat' : 'belum';
            }

            $rincian[] = [
                'judul' => $t->title,
                'kondisi' => $kondisi,
                'batas' => $batas->locale('id')->translatedFormat('j M Y, H:i'),
                'terlambat' => (bool) $t->is_late,
                'skor' => $punyaNilai ? $this->angka((float) $t->score) : null,
                'max' => $max,
                'persen' => $punyaNilai ? round(((float) $t->score / $max) * 100) : null,
                'feedback' => $t->feedback,
                'dinilaiPada' => $t->graded_at
                    ? Carbon::parse($t->graded_at)->locale('id')->translatedFormat('j M Y')
                    : null,
            ];
        }

        $nilai = $persen ? round(array_sum($persen) / count($persen), 2) : null;
        [$huruf, $mutu] = $nilai === null ? [null, null] : $this->konversi($nilai);

        return [
            'id' => $c->id,
            'kode' => $c->code,
            'nama' => $c->name,
            'dosen' => $c->lecturer?->name ?? '-',
            'sks' => (int) $c->sks,
            'jumlahTugas' => $tugas->count(),
            'dikumpul' => $dikumpul,
            'dinilai' => count($persen),
            'nilai' => $nilai,
            'huruf' => $huruf,
            'mutu' => $mutu,
            'status' => $nilai === null ? 'belum' : ($nilai >= self::BATAS_LULUS ? 'lulus' : 'perbaikan'),
            'rincian' => $rincian,
        ];
    }

    private function ringkasan(Collection $semua): array
    {
        $bernilai = $semua->whereNotNull('nilai');
        $sksBernilai = (int) $bernilai->sum('sks');
        $totalMutu = (float) $bernilai->sum(fn (array $m) => $m['sks'] * $m['mutu']);

        return [
            'ips' => $sksBernilai > 0 ? round($totalMutu / $sksBernilai, 2) : null,
            'rata' => $bernilai->isNotEmpty() ? round($bernilai->avg('nilai'), 2) : null,
            'totalMutu' => $totalMutu,
            'sksAmbil' => (int) $semua->sum('sks'),
            'sksBernilai' => $sksBernilai,
            'sksLulus' => (int) $semua->where('status', 'lulus')->sum('sks'),
            'mkTotal' => $semua->count(),
            'mkBernilai' => $bernilai->count(),
            'mkLulus' => $semua->where('status', 'lulus')->count(),
            'mkPerbaikan' => $semua->where('status', 'perbaikan')->count(),
            'tugasTotal' => (int) $semua->sum('jumlahTugas'),
            'tugasDinilai' => (int) $semua->sum('dinilai'),
            'menunggu' => (int) $semua->sum(fn (array $m) => $m['dikumpul'] - $m['dinilai']),
        ];
    }

    /** @return array{0: string, 1: float} [huruf, bobot mutu] */
    private function konversi(float $nilai): array
    {
        foreach (self::SKALA as [$batas, $huruf, $mutu]) {
            if ($nilai >= $batas) {
                return [$huruf, $mutu];
            }
        }

        return ['E', 0.0];
    }

    private function angka(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2, ',', ''), '0'), ',');
    }
}