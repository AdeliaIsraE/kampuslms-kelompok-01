<?php

namespace Database\Factories;

use App\Models\Grade;
use App\Models\Submission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Grade>
 *
 * Default: nilai 60-100 yang SUDAH diberikan dosen pengampu, tetapi belum
 * ditampilkan ke mahasiswa (is_published = false, sama dengan default DB).
 *
 * Contoh:
 *   Grade::factory()->create(['submission_id' => $submission->id]);
 *   Grade::factory()->published()->create(['submission_id' => $submission->id]);
 *
 * Catatan: factory ini langsung menyimpan ke database. Event NilaiDiberikan
 * (dan notifikasinya) hanya dipicu oleh GradeSubmission, jadi TIDAK ikut
 * terpicu di sini.
 */
class GradeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'submission_id' => Submission::factory(),
            // graded_by wajib (NOT NULL): default-nya dosen pengampu MK dari
            // tugas pengumpulan ini. Urutan kunci penting: setelah 'submission_id'.
            'graded_by' => fn (array $attrs) => Submission::findOrFail($attrs['submission_id'])
                ->assignment->course->lecturer_id,
            'score' => fake()->randomFloat(2, 60, 100),
            'feedback' => fake('id_ID')->sentence(8),
            'is_published' => false,
            'graded_at' => now(),
        ];
    }

    /** Nilai yang sudah ditampilkan ke mahasiswa. */
    public function published(): static
    {
        return $this->state(fn () => ['is_published' => true]);
    }
}