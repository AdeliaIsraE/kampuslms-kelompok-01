<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\Material;
use App\Models\Submission;
use App\Models\User;
use App\Support\AdminDashboardCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(): View
    {
        /*
        |--------------------------------------------------------------------------
        | Statistik Utama (Cache 10 Menit)
        |--------------------------------------------------------------------------
        */
        $statistik = Cache::remember(
            AdminDashboardCache::key(),
            now()->addMinutes(10),
            function () {
                $totalDosen = User::where('role', 'dosen')->count();
                $totalMahasiswa = User::where('role', 'mahasiswa')->count();

                $totalMataKuliah = Course::count();
                $totalTugas = Assignment::count();

                /*
                |--------------------------------------------------------------------------
                | Jumlah Aktivitas Hari Ini
                |--------------------------------------------------------------------------
                */
                $hariIni = now()->startOfDay();

                $aktivitasHariIni = Material::where('created_at', '>=', $hariIni)->count()
                    + Assignment::where('status', 'published')
                        ->where('created_at', '>=', $hariIni)->count()
                    + Submission::where('submitted_at', '>=', $hariIni)->count();

                return compact(
                    'totalDosen',
                    'totalMahasiswa',
                    'totalMataKuliah',
                    'totalTugas',
                    'aktivitasHariIni',
                );
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