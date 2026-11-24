<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseTest extends TestCase
{
    use RefreshDatabase;

    private function dataMataKuliah(User $dosen, array $override = []): array
    {
        return array_merge([
            'code' => 'SI9999001',
            'name' => 'Kecerdasan Buatan',
            'description' => 'Pengantar kecerdasan buatan.',
            'sks' => 3,
            'lecturer_id' => $dosen->id,
            'status' => 'active',
        ], $override);
    }

    public function test_admin_dapat_membuat_mata_kuliah(): void
    {
        $admin = User::factory()->admin()->create();
        $dosen = User::factory()->dosen()->create();

        $this->actingAs($admin)
            ->post(route('admin.mata-kuliah.store'), $this->dataMataKuliah($dosen))
            ->assertRedirect(route('admin.mata-kuliah.index'));

        $this->assertDatabaseHas('courses', [
            'code' => 'SI9999001',
            'name' => 'Kecerdasan Buatan',
            'lecturer_id' => $dosen->id,
        ]);
    }

    public function test_mata_kuliah_ditolak_jika_pengampunya_bukan_dosen(): void
    {
        $admin = User::factory()->admin()->create();
        $mahasiswa = User::factory()->mahasiswa()->create();

        $this->actingAs($admin)
            ->post(route('admin.mata-kuliah.store'), $this->dataMataKuliah($mahasiswa))
            ->assertSessionHasErrors('lecturer_id');

        $this->assertDatabaseCount('courses', 0);
    }

    public function test_dosen_tidak_dapat_membuat_mata_kuliah(): void
    {
        $dosen = User::factory()->dosen()->create();

        $this->actingAs($dosen)
            ->post(route('admin.mata-kuliah.store'), $this->dataMataKuliah($dosen))
            ->assertForbidden();

        $this->assertDatabaseCount('courses', 0);
    }

    public function test_mahasiswa_tidak_dapat_membuat_mata_kuliah(): void
    {
        $dosen = User::factory()->dosen()->create();
        $mahasiswa = User::factory()->mahasiswa()->create();

        $this->actingAs($mahasiswa)
            ->post(route('admin.mata-kuliah.store'), $this->dataMataKuliah($dosen))
            ->assertForbidden();

        $this->assertDatabaseCount('courses', 0);
    }

    public function test_dosen_pengampu_tidak_dapat_mengubah_atau_menghapus_mata_kuliahnya(): void
    {
        $course = Course::factory()->create(['name' => 'Nama Asli']);
        $dosen = $course->lecturer;

        $this->actingAs($dosen)
            ->put(route('admin.mata-kuliah.update', $course), $this->dataMataKuliah($dosen, [
                'code' => $course->code,
                'name' => 'Diretas',
            ]))
            ->assertForbidden();

        $this->actingAs($dosen)
            ->delete(route('admin.mata-kuliah.destroy', $course))
            ->assertForbidden();

        $this->assertDatabaseHas('courses', ['id' => $course->id, 'name' => 'Nama Asli']);
    }

    public function test_mahasiswa_hanya_melihat_mata_kuliah_yang_diikuti_dan_aktif(): void
    {
        $mahasiswa = User::factory()->mahasiswa()->create();

        $diikuti = Course::factory()->create(['name' => 'Kuliah Yang Diikuti']);
        $lain = Course::factory()->create(['name' => 'Kuliah Orang Lain']);
        $draft = Course::factory()->create(['name' => 'Kuliah Draft Tersembunyi', 'status' => 'draft']);

        $diikuti->students()->attach($mahasiswa->id, ['enrolled_at' => now()]);
        $draft->students()->attach($mahasiswa->id, ['enrolled_at' => now()]);

        $this->actingAs($mahasiswa)
            ->get(route('mahasiswa.mata-kuliah.index'))
            ->assertOk()
            ->assertSee('Kuliah Yang Diikuti')
            ->assertDontSee('Kuliah Orang Lain')
            ->assertDontSee('Kuliah Draft Tersembunyi');
    }

    public function test_mahasiswa_tidak_dapat_membuka_detail_mata_kuliah_yang_tidak_diikuti(): void
    {
        $mahasiswa = User::factory()->mahasiswa()->create();
        $course = Course::factory()->create();

        $this->actingAs($mahasiswa)
            ->get(route('mahasiswa.mata-kuliah.show', $course))
            ->assertForbidden();
    }
}