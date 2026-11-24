<?php

namespace Database\Factories;

use App\Models\Assignment;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;

/**
 * @extends Factory<Submission>
 *
 * Default: satu pengumpulan tepat waktu dari seorang mahasiswa.
 *
 * PERHATIAN: mahasiswa bawaan factory TIDAK otomatis terdaftar di mata
 * kuliah tugasnya. Untuk test yang melewati policy, daftarkan sendiri:
 *   $course->students()->attach($mhs->id, ['enrolled_at' => now()]);
 *
 * Contoh:
 *   Submission::factory()->create(['assignment_id' => $tugas->id, 'user_id' => $mhs->id]);
 *   Submission::factory()->late()->withStoredFile()->create([...]);
 */
class SubmissionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'assignment_id' => Assignment::factory(),
            'user_id' => User::factory()->mahasiswa(),
            // Pola path sama dengan SubmitAssignment: submissions/{assignment_id}/{nama-acak}
            'file_path' => fn (array $attrs) => "submissions/{$attrs['assignment_id']}/" . fake()->uuid() . '.pdf',
            'original_name' => 'tugas_' . fake()->word() . '.pdf',
            'file_size' => fake()->numberBetween(50_000, 3_000_000),
            'note' => fake()->boolean(30) ? fake('id_ID')->sentence() : null,
            'submitted_at' => now(),
            'is_late' => false,
        ];
    }

    /**
     * Ditandai terlambat. Pasangkan dengan tugas ber-state past() supaya
     * submitted_at (now) memang berada setelah tenggat.
     */
    public function late(): static
    {
        return $this->state(fn () => ['is_late' => true]);
    }

    /**
     * Tulis berkas sungguhan ke disk 'local' setelah pengumpulan dibuat.
     * WAJIB didahului Storage::fake('local') di test, kalau tidak berkas
     * akan tertulis ke storage/app/private yang asli.
     */
    public function withStoredFile(): static
    {
        return $this->afterCreating(function (Submission $submission) {
            Storage::disk('local')->put($submission->file_path, 'isi berkas uji');
        });
    }
}