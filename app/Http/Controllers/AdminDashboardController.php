<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\User;
use App\Support\AdminDashboardCache;
use App\Support\JumlahNotifikasi;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(): View
    {
        /*
        |--------------------------------------------------------------------------
        | Statistik Utama (Cache 10 Menit)
        |--------------------------------------------------------------------------
        | Semua angka diambil dalam SATU query (subquery scalar), bukan 7 query
        | count() terpisah. Bentuk array hasilnya sama seperti sebelumnya, jadi
        | view dan AdminDashboardCache::forget() tidak berubah.
        */
        $statistik = Cache::remember(
            AdminDashboardCache::key(),
            now()->addMinutes(10),
            function () {
                $query = DB::query()
                    ->selectSub(User::where('role', 'dosen')->selectRaw('count(*)'), 'total_dosen')
                    ->selectSub(User::where('role', 'mahasiswa')->selectRaw('count(*)'), 'total_mahasiswa')
                    ->selectSub(Course::query()->selectRaw('count(*)'), 'total_mata_kuliah')
                    ->selectSub(Assignment::query()->selectRaw('count(*)'), 'total_tugas');

                // Aktivitas hari ini: definisi yang sama dengan angka lonceng admin.
                foreach (JumlahNotifikasi::subkueriAktivitasHariIni() as $nama => $sub) {
                    $query->selectSub($sub, $nama);
                }

                $baris = $query->first();

                return [
                    'totalDosen'       => (int) $baris->total_dosen,
                    'totalMahasiswa'   => (int) $baris->total_mahasiswa,
                    'totalMataKuliah'  => (int) $baris->total_mata_kuliah,
                    'totalTugas'       => (int) $baris->total_tugas,
                    'aktivitasHariIni' => JumlahNotifikasi::jumlahkan($baris),
                ];
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Ringkasan Mata Kuliah (Tetap Menggunakan Pagination)
        |--------------------------------------------------------------------------
        */
        $ringkasanMataKuliah = Course::with('lecturer')
            ->withCount('students')
            ->orderBy('name')
            ->paginate(10);

        /*
        |--------------------------------------------------------------------------
        | Kirim Data ke Dashboard Admin
        |--------------------------------------------------------------------------
        */
        return view('admin.dashboard', array_merge(
            $statistik,
            compact('ringkasanMataKuliah'),
        ));
    }
}