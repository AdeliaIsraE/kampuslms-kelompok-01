<?php

namespace Database\Factories;

use App\Models\Assignment;
use App\Models\Course;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assignment>
 *
 * Default: tugas published, tenggat di MASA DEPAN, keterlambatan diizinkan.
 * Tenggat sengaja tidak acak ke masa lalu supaya test deadline tidak flaky.
 *
 * Contoh:
 *   Assignment::factory()->for($course)->create();                // aktif
 *   Assignment::factory()->past()->disallowLate()->create([...]); // sudah lewat, tidak terima terlambat
 *   Assignment::factory()->draft()->create([...]);                // belum terbit
 *
 * Catatan: tugas yang dibuat/diubah menjadi 'published' memicu event
 * TugasDipublikasikan (lihat Assignment::booted). Di test notifikasi, panggil
 * Notification::fake() SEBELUM membuat tugasnya.
 */
class AssignmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            // created_by wajib (NOT NULL): default-nya dosen pengampu MK tugas ini.
            // Urutan kunci penting: harus setelah 'course_id'.
            'created_by' => fn (array $attrs) => Course::findOrFail($attrs['course_id'])->lecturer_id,
            'title' => 'Tugas ' . fake('id_ID')->word(),
            'instructions' => fake('id_ID')->paragraph(),
            'due_at' => fake()->dateTimeBetween('+2 days', '+2 weeks'),
            'max_score' => 100,
            'allow_late' => true,
            'status' => 'published',
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['status' => 'published']);
    }

    public function past(): static
    {
        return $this->state(fn () => [
            'due_at' => fake()->dateTimeBetween('-3 weeks', '-1 week'),
            'status' => 'published',
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => 'draft',
            'due_at' => fake()->dateTimeBetween('+1 week', '+4 weeks'),
        ]);
    }

    /** Tenggat tertentu, untuk test yang bergantung pada waktu. */
    public function dueAt(CarbonInterface|string $when): static
    {
        return $this->state(fn () => ['due_at' => $when]);
    }

    /** Tidak menerima pengumpulan setelah tenggat (allow_late = false). */
    public function disallowLate(): static
    {
        return $this->state(fn () => ['allow_late' => false]);
    }
}