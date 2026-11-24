<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function dataTugas(array $override = []): array
    {
        return array_merge([
            'title' => 'Tugas Baru',
            'instructions' => 'Kerjakan soal pada modul.',
            'due_at' => now()->addDays(3)->format('Y-m-d H:i:s'),
            'max_score' => 100,
            'allow_late' => 1,
            'status' => 'published',
        ], $override);
    }

    public function test_dosen_pengampu_dapat_membuat_tugas(): void
    {
        $course = Course::factory()->create();

        $this->actingAs($course->lecturer)
            ->post(route('dosen.mata-kuliah.tugas.store', $course), $this->dataTugas())
            ->assertRedirect();

        $this->assertDatabaseHas('assignments', [
            'course_id' => $course->id,
            'title' => 'Tugas Baru',
            'created_by' => $course->lecturer_id,
            'status' => 'published',
        ]);
    }

    public function test_dosen_lain_tidak_dapat_membuat_tugas(): void
    {
        $course = Course::factory()->create();
        $dosenLain = User::factory()->dosen()->create();

        $this->actingAs($dosenLain)
            ->post(route('dosen.mata-kuliah.tugas.store', $course), $this->dataTugas())
            ->assertForbidden();

        $this->assertDatabaseCount('assignments', 0);
    }

    public function test_tugas_dengan_tenggat_di_masa_lalu_ditolak(): void
    {
        $course = Course::factory()->create();

        $this->actingAs($course->lecturer)
            ->post(route('dosen.mata-kuliah.tugas.store', $course), $this->dataTugas([
                'due_at' => now()->subDay()->format('Y-m-d H:i:s'),
            ]))
            ->assertSessionHasErrors('due_at');

        $this->assertDatabaseCount('assignments', 0);
    }

    public function test_mahasiswa_tidak_dapat_membuka_tugas_draft(): void
    {
        $course = Course::factory()->create();
        $draft = Assignment::factory()->draft()->for($course)->create();
        $mahasiswa = User::factory()->mahasiswa()->create();
        $course->students()->attach($mahasiswa->id, ['enrolled_at' => now()]);

        $this->actingAs($mahasiswa)
            ->get(route('mahasiswa.tugas.show', $draft))
            ->assertForbidden();
    }

    public function test_mahasiswa_tidak_terdaftar_tidak_dapat_mengunduh_lampiran_tugas(): void
    {
        $course = Course::factory()->create();
        $tugas = Assignment::factory()->for($course)->create([
            'file_path' => "assignments/{$course->id}/soal.pdf",
            'original_name' => 'soal.pdf',
        ]);
        $orangLuar = User::factory()->mahasiswa()->create();

        $this->actingAs($orangLuar)
            ->get(route('tugas.lampiran', $tugas))
            ->assertForbidden();
    }
}