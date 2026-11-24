<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrollmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_dosen_pengampu_dapat_mendaftarkan_mahasiswa(): void
    {
        $course = Course::factory()->create();
        $budi = User::factory()->mahasiswa()->create();
        $ani = User::factory()->mahasiswa()->create();

        $this->actingAs($course->lecturer)
            ->post(route('dosen.mata-kuliah.mahasiswa.store', $course), [
                'student_ids' => [$budi->id, $ani->id],
            ])
            ->assertRedirect(route('dosen.mata-kuliah.show', $course));

        $this->assertDatabaseHas('course_user', ['course_id' => $course->id, 'user_id' => $budi->id]);
        $this->assertDatabaseHas('course_user', ['course_id' => $course->id, 'user_id' => $ani->id]);
    }

    public function test_dosen_lain_tidak_dapat_mendaftarkan_mahasiswa(): void
    {
        $course = Course::factory()->create();
        $dosenLain = User::factory()->dosen()->create();
        $mahasiswa = User::factory()->mahasiswa()->create();

        $this->actingAs($dosenLain)
            ->post(route('dosen.mata-kuliah.mahasiswa.store', $course), [
                'student_ids' => [$mahasiswa->id],
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('course_user', 0);
    }

    public function test_pendaftaran_ditolak_jika_id_bukan_mahasiswa(): void
    {
        $course = Course::factory()->create();
        $dosenLain = User::factory()->dosen()->create();

        $this->actingAs($course->lecturer)
            ->post(route('dosen.mata-kuliah.mahasiswa.store', $course), [
                'student_ids' => [$dosenLain->id],
            ])
            ->assertSessionHasErrors('student_ids.0');

        $this->assertDatabaseCount('course_user', 0);
    }

    public function test_mendaftarkan_mahasiswa_yang_sama_dua_kali_tidak_menggandakan_baris(): void
    {
        $course = Course::factory()->create();
        $mahasiswa = User::factory()->mahasiswa()->create();
        $payload = ['student_ids' => [$mahasiswa->id]];

        $this->actingAs($course->lecturer)
            ->post(route('dosen.mata-kuliah.mahasiswa.store', $course), $payload);
        $this->actingAs($course->lecturer)
            ->post(route('dosen.mata-kuliah.mahasiswa.store', $course), $payload);

        $this->assertSame(1, $course->students()->count());
    }

    public function test_dosen_pengampu_dapat_mengeluarkan_mahasiswa(): void
    {
        $course = Course::factory()->create();
        $mahasiswa = User::factory()->mahasiswa()->create();
        $course->students()->attach($mahasiswa->id, ['enrolled_at' => now()]);

        $this->actingAs($course->lecturer)
            ->delete(route('dosen.mata-kuliah.mahasiswa.destroy', [$course, $mahasiswa]))
            ->assertRedirect(route('dosen.mata-kuliah.show', $course));

        $this->assertDatabaseMissing('course_user', [
            'course_id' => $course->id,
            'user_id' => $mahasiswa->id,
        ]);
    }

    public function test_dosen_lain_tidak_dapat_mengeluarkan_mahasiswa(): void
    {
        $course = Course::factory()->create();
        $dosenLain = User::factory()->dosen()->create();
        $mahasiswa = User::factory()->mahasiswa()->create();
        $course->students()->attach($mahasiswa->id, ['enrolled_at' => now()]);

        $this->actingAs($dosenLain)
            ->delete(route('dosen.mata-kuliah.mahasiswa.destroy', [$course, $mahasiswa]))
            ->assertForbidden();

        $this->assertDatabaseHas('course_user', [
            'course_id' => $course->id,
            'user_id' => $mahasiswa->id,
        ]);
    }
}