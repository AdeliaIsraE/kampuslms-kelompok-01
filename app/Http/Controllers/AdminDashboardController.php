<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\Material;
use App\Models\Submission;
use App\Models\User;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(): View
    {
        /*
        |--------------------------------------------------------------------------
        | Statistik Utama
        |--------------------------------------------------------------------------
        */
        $totalDosen = User::where('role', 'dosen')->count();
        $totalMahasiswa = User::where('role', 'mahasiswa')->count();

        $totalMataKuliah = Course::count();
        $totalTugas = Assignment::count();

        /*
        |--------------------------------------------------------------------------
        | Lonceng Notifikasi: jumlah aktivitas hari ini
        | (materi diunggah + tugas diterbitkan + pengumpulan masuk;
        | aturannya sama dengan banner di halaman Notifikasi)
        |--------------------------------------------------------------------------
        */
        $hariIni = now()->startOfDay();

        $aktivitasHariIni = Material::where('created_at', '>=', $hariIni)->count()
            + Assignment::where('status', 'published')->where('created_at', '>=', $hariIni)->count()
            + Submission::where('submitted_at', '>=', $hariIni)->count();

        /*
        |--------------------------------------------------------------------------
        | Ringkasan Mata Kuliah
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
        return view('admin.dashboard', compact(
            'totalDosen',
            'totalMahasiswa',
            'totalMataKuliah',
            'totalTugas',
            'aktivitasHariIni',
            'ringkasanMataKuliah',
        ));
    }
}
