<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Support\AdminDashboardCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CourseEnrollmentController extends Controller
{
    /**
     * POST /{admin|dosen}/mata-kuliah/{mata_kuliah}/mahasiswa
     * Memasukkan satu atau banyak mahasiswa ke mata kuliah.
     */
    public function store(Request $request, Course $mata_kuliah)
    {
        Gate::authorize('manageEnrollment', $mata_kuliah);

        $data = $request->validate([
            'student_ids'   => ['required', 'array', 'min:1'],
            'student_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('users', 'id')->where('role', 'mahasiswa')->whereNull('deleted_at'),
            ],
        ], [
            'student_ids.required'   => 'Pilih minimal satu mahasiswa.',
            'student_ids.min'        => 'Pilih minimal satu mahasiswa.',
            'student_ids.*.exists'   => 'Ada mahasiswa yang dipilih tidak ditemukan di sistem.',
            'student_ids.*.integer'  => 'Data mahasiswa tidak valid.',
            'student_ids.*.distinct' => 'Ada mahasiswa yang dipilih lebih dari sekali.',
        ]);

        $ids = collect($data['student_ids'])->map(fn ($id) => (int) $id)->unique()->values();

        // Pisahkan mahasiswa yang sudah terdaftar.
        $alreadyEnrolled = $mata_kuliah->students()
            ->whereIn('users.id', $ids)
            ->pluck('users.id');

        $newIds = $ids->diff($alreadyEnrolled)->values();

        if ($newIds->isNotEmpty()) {
            $mata_kuliah->students()->attach(
                $newIds->mapWithKeys(fn ($id) => [$id => ['enrolled_at' => now()]])->all()
            );

            // Hapus cache karena jumlah mahasiswa pada mata kuliah berubah.
            AdminDashboardCache::forget();
        }

        $added = $newIds->count();

        return redirect()
            ->route($this->routePrefix($request) . '.show', $mata_kuliah)
            ->with('success', $added > 0
                ? "{$added} mahasiswa berhasil ditambahkan ke mata kuliah."
                : 'Mahasiswa yang dipilih sudah terdaftar pada mata kuliah ini.');
    }

    /**
     * DELETE /{admin|dosen}/mata-kuliah/{mata_kuliah}/mahasiswa/{student}
     * Mengeluarkan satu mahasiswa dari mata kuliah.
     */
    public function destroy(Request $request, Course $mata_kuliah, int $student)
    {
        Gate::authorize('manageEnrollment', $mata_kuliah);

        // detach() mengembalikan jumlah baris pivot yang terhapus.
        $removed = $mata_kuliah->students()->detach($student);

        if ($removed > 0) {
            // Hapus cache karena jumlah mahasiswa pada mata kuliah berubah.
            AdminDashboardCache::forget();
        }

        return redirect()
            ->route($this->routePrefix($request) . '.show', $mata_kuliah)
            ->with(
                $removed > 0 ? 'success' : 'error',
                $removed > 0
                    ? 'Mahasiswa dikeluarkan dari mata kuliah.'
                    : 'Mahasiswa tersebut tidak terdaftar pada mata kuliah ini.'
            );
    }

    /**
     * Prefix nama route sesuai peran.
     */
    private function routePrefix(Request $request): string
    {
        return $request->user()->role . '.mata-kuliah';
    }
}