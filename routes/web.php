<?php

use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminNotifikasiController;
use App\Http\Controllers\DosenNotifikasiController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\CourseEnrollmentController;
use App\Http\Controllers\GradeController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\MahasiswaDashboardController;
use App\Http\Controllers\MahasiswaNilaiController;
use App\Http\Controllers\MahasiswaNotifikasiController;
use App\Http\Controllers\NotifikasiController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\SubmissionController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// ---------- Publik / login ----------

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route(Auth::user()->role . '.dashboard');
    }

    return view('welcome');
})->name('login');

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:5,1')
    ->name('login.store');

Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::get('/tentang', function () {
    return view('tentang');
})->name('tentang');

// ---------- Reset kata sandi (hanya untuk yang belum login) ----------
// Nama route password.request / password.reset dipakai oleh Laravel
// saat membuat tautan di email, jadi jangan diganti.

Route::middleware('guest')->group(function () {

    Route::get('/lupa-password', [PasswordResetController::class, 'requestForm'])
        ->name('password.request');

    Route::post('/lupa-password', [PasswordResetController::class, 'sendLink'])
        ->middleware('throttle:5,1')
        ->name('password.email');

    Route::get('/reset-password/{token}', [PasswordResetController::class, 'resetForm'])
        ->name('password.reset');

    Route::post('/reset-password', [PasswordResetController::class, 'reset'])
        ->middleware('throttle:5,1')
        ->name('password.update');
});

// ---------- Perlu login ----------

Route::middleware('auth')->group(function () {

    Route::get('/dashboard', function () {
        return redirect()->route(Auth::user()->role . '.dashboard');
    })->name('dashboard');

    Route::get('/pengumpulan/{submission}/unduh', [SubmissionController::class, 'download'])
        ->name('pengumpulan.unduh');

    // Unduh berkas pengumpulan versi LAMA (riwayat revisi).
    // scopeBindings(): {revision} hanya di-resolve dari revisi milik {submission}.
    // SubmissionController@downloadRevision juga memeriksa kepemilikannya.
    Route::get('/pengumpulan/{submission}/revisi/{revision}/unduh', [SubmissionController::class, 'downloadRevision'])
        ->scopeBindings()
        ->name('pengumpulan.revisi.unduh');

    // Unduh berkas materi (disimpan di disk privat). Semua peran yang login
    // boleh mencoba; hak akses sebenarnya diperiksa di MaterialController@download.
    Route::get('/materi/{material}/unduh', [MaterialController::class, 'download'])
        ->name('materi.unduh');

    // Unduh lampiran soal tugas (disk privat). Hak akses sebenarnya diperiksa
    // di AssignmentController@download (AssignmentPolicy::view).
    Route::get('/tugas/{assignment}/lampiran', [AssignmentController::class, 'download'])
        ->name('tugas.lampiran');

    // Jumlah pada lonceng notifikasi di navbar (polling tiap 30 detik).
    // Berlaku untuk semua peran; angkanya dihitung per peran di JumlahNotifikasi.
    Route::get('/notifikasi/belum-dibaca', [NotifikasiController::class, 'belumDibaca'])
        ->middleware('throttle:60,1')
        ->name('notifikasi.belum-dibaca');


    // =========================================================
    // ADMIN
    // =========================================================

    Route::middleware('role:admin')
        ->prefix('admin')
        ->name('admin.')
        ->group(function () {

            Route::get('/dashboard', [AdminDashboardController::class, 'index'])
                ->name('dashboard');

            // Pusat notifikasi: feed gabungan materi, tugas, dan pengumpulan.
            // Nama lengkap: admin.notifikasi
            Route::get('/notifikasi', [AdminNotifikasiController::class, 'index'])
                ->name('notifikasi');

            // Tandai semua aktivitas sampai saat ini sebagai sudah dibaca.
            // Nama lengkap: admin.notifikasi.dibaca-semua
            Route::post('/notifikasi/dibaca-semua', [AdminNotifikasiController::class, 'dibacaSemua'])
                ->name('notifikasi.dibaca-semua');

            // Tandai satu aktivitas sebagai sudah dibaca (aktivitas lalu hilang dari daftar).
            // Nama lengkap: admin.notifikasi.dibaca
            Route::post('/notifikasi/{jenis}/{id}/dibaca', [AdminNotifikasiController::class, 'dibaca'])
                ->whereIn('jenis', ['materi', 'tugas', 'pengumpulan'])
                ->whereNumber('id')
                ->name('notifikasi.dibaca');

            Route::resource('users', UserController::class);

            Route::resource('mata-kuliah', CourseController::class);

            // Pendaftaran mahasiswa ke mata kuliah (admin: semua MK).
            // Nama lengkap: admin.mata-kuliah.mahasiswa.store / .destroy
            Route::post('mata-kuliah/{mata_kuliah}/mahasiswa', [CourseEnrollmentController::class, 'store'])
                ->name('mata-kuliah.mahasiswa.store');

            Route::delete('mata-kuliah/{mata_kuliah}/mahasiswa/{student}', [CourseEnrollmentController::class, 'destroy'])
                ->whereNumber('student')
                ->name('mata-kuliah.mahasiswa.destroy');

            // Materi: admin CRUD penuh (sesuai matriks hak akses). Otorisasi per
            // MK diperiksa di MaterialPolicy (admin lolos lewat isManagedBy).
            // Nama lengkap: admin.mata-kuliah.materi.{index,create,store},
            //               admin.materi.{show,edit,update,destroy}
            // Tugas: admin CRUD penuh (sesuai matriks hak akses), otorisasi di AssignmentPolicy.
            //               admin.mata-kuliah.tugas.{index,create,store},
            //               admin.tugas.{show,edit,update,destroy}
            Route::scopeBindings()->group(function () {

                Route::resource('mata-kuliah.materi', MaterialController::class)
                    ->parameters([
                        'mata-kuliah' => 'course',
                        'materi' => 'material',
                    ])
                    ->shallow();

                Route::resource('mata-kuliah.tugas', AssignmentController::class)
                    ->parameters([
                        'mata-kuliah' => 'course',
                        'tugas' => 'assignment',
                    ])
                    ->shallow();
            });
        });


    // =========================================================
    // DOSEN
    // =========================================================

    Route::middleware('role:dosen')
        ->prefix('dosen')
        ->name('dosen.')
        ->group(function () {

            Route::view('/dashboard', 'dosen.dashboard')
                ->name('dashboard');

            // Pusat notifikasi dosen: pengumpulan di MK yang diampu.
            // Nama lengkap: dosen.notifikasi
            Route::get('/notifikasi', [DosenNotifikasiController::class, 'index'])
                ->name('notifikasi');

            // Tandai pengumpulan sebagai sudah dibaca (hilang dari daftar).
            // Nama lengkap: dosen.notifikasi.dibaca-semua, dosen.notifikasi.dibaca
            Route::post('/notifikasi/dibaca-semua', [DosenNotifikasiController::class, 'dibacaSemua'])
                ->name('notifikasi.dibaca-semua');

            Route::post('/notifikasi/{id}/dibaca', [DosenNotifikasiController::class, 'dibaca'])
                ->whereNumber('id')
                ->name('notifikasi.dibaca');

            // Dosen hanya melihat MK yang diampu. Mengubah MK = admin saja
            // (CoursePolicy::update), jadi edit/update tidak dibuka di sini.
            Route::resource('mata-kuliah', CourseController::class)
                ->only([
                    'index',
                    'show',
                ]);

            // Dosen mengelola peserta MK yang diampunya SENDIRI.
            // Middleware role:dosen hanya memastikan perannya; kepemilikan MK
            // diperiksa di CourseEnrollmentController lewat
            // Gate::authorize('manageEnrollment') -> dosen lain mendapat 403.
            // Nama lengkap: dosen.mata-kuliah.mahasiswa.store / .destroy
            Route::post('mata-kuliah/{mata_kuliah}/mahasiswa', [CourseEnrollmentController::class, 'store'])
                ->name('mata-kuliah.mahasiswa.store');

            Route::delete('mata-kuliah/{mata_kuliah}/mahasiswa/{student}', [CourseEnrollmentController::class, 'destroy'])
                ->whereNumber('student')
                ->name('mata-kuliah.mahasiswa.destroy');

            // Penilaian pengumpulan: hanya dosen. Kepemilikan MK diperiksa di
            // StoreGradeRequest lewat SubmissionPolicy::grade -> dosen lain dan
            // admin mendapat 403 (admin hanya boleh MELIHAT nilai).
            // Nama lengkap: dosen.pengumpulan.nilai
            Route::put('pengumpulan/{submission}/nilai', [GradeController::class, 'update'])
                ->name('pengumpulan.nilai');

            Route::scopeBindings()->group(function () {

                Route::resource('mata-kuliah.materi', MaterialController::class)
                    ->parameters([
                        'mata-kuliah' => 'course',
                        'materi' => 'material',
                    ])
                    ->shallow();

                Route::resource('mata-kuliah.tugas', AssignmentController::class)
                    ->parameters([
                        'mata-kuliah' => 'course',
                        'tugas' => 'assignment',
                    ])
                    ->shallow();
            });
        });


    // =========================================================
    // MAHASISWA
    // =========================================================

    Route::middleware('role:mahasiswa')
        ->prefix('mahasiswa')
        ->name('mahasiswa.')
        ->group(function () {

            Route::get('/dashboard', [MahasiswaDashboardController::class, 'index'])
                ->name('dashboard');

            // Pusat notifikasi mahasiswa: notifikasi database (tugas baru, nilai) + tandai dibaca.
            // Kepemilikan dijaga di controller: hanya notifikasi milik user yang login.
            // Nama lengkap: mahasiswa.notifikasi, mahasiswa.notifikasi.dibaca,
            //               mahasiswa.notifikasi.dibaca-semua
            Route::get('/notifikasi', [MahasiswaNotifikasiController::class, 'index'])
                ->name('notifikasi');

            Route::post('/notifikasi/dibaca-semua', [MahasiswaNotifikasiController::class, 'dibacaSemua'])
                ->name('notifikasi.dibaca-semua');

            Route::post('/notifikasi/{id}/dibaca', [MahasiswaNotifikasiController::class, 'dibaca'])
                ->whereUuid('id')
                ->name('notifikasi.dibaca');

            // Rekap nilai: nilai tugas per mata kuliah milik mahasiswa yang login.
            // Nama lengkap: mahasiswa.nilai
            Route::get('/nilai', [MahasiswaNilaiController::class, 'index'])
                ->name('nilai');

            Route::resource('mata-kuliah', CourseController::class)
                ->only([
                    'index',
                    'show',
                ]);

            Route::scopeBindings()->group(function () {

                Route::resource('mata-kuliah.materi', MaterialController::class)
                    ->only([
                        'index',
                        'show',
                    ])
                    ->parameters([
                        'mata-kuliah' => 'course',
                        'materi' => 'material',
                    ])
                    ->shallow();

                Route::resource('mata-kuliah.tugas', AssignmentController::class)
                    ->only([
                        'index',
                        'show',
                    ])
                    ->parameters([
                        'mata-kuliah' => 'course',
                        'tugas' => 'assignment',
                    ])
                    ->shallow();
            });

            Route::post('/tugas/{assignment}/kumpul', [SubmissionController::class, 'store'])
                ->name('tugas.kumpul');
        });
});