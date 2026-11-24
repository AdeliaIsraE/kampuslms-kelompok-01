<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Material;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MaterialTest extends TestCase
{
    use RefreshDatabase;

    private function dataLink(array $override = []): array
    {
        return array_merge([
            'title' => 'Slide Pertemuan 1',
            'type' => 'link',
            'external_url' => 'https://example.com/slide',
        ], $override);
    }

    public function test_dosen_pengampu_dapat_menambah_materi_tautan(): void
    {
        $course = Course::factory()->create();

        $this->actingAs($course->lecturer)
            ->post(route('dosen.mata-kuliah.materi.store', $course), $this->dataLink())
            ->assertRedirect(route('dosen.mata-kuliah.materi.index', $course));

        $this->assertDatabaseHas('materials', [
            'course_id' => $course->id,
            'title' => 'Slide Pertemuan 1',
            'type' => 'link',
            'uploaded_by' => $course->lecturer_id,
        ]);
    }

    public function test_dosen_pengampu_dapat_mengunggah_materi_berkas(): void
    {
        Storage::fake('local');
        $course = Course::factory()->create();

        $this->actingAs($course->lecturer)
            ->post(route('dosen.mata-kuliah.materi.store', $course), [
                'title' => 'Modul PDF',
                'type' => 'file',
                'file' => UploadedFile::fake()->create('modul.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect();

        $material = Material::where('course_id', $course->id)->firstOrFail();

        $this->assertSame('modul.pdf', $material->original_name);
        Storage::disk('local')->assertExists($material->file_path);
    }

    public function test_dosen_lain_tidak_dapat_menambah_materi(): void
    {
        $course = Course::factory()->create();
        $dosenLain = User::factory()->dosen()->create();

        $this->actingAs($dosenLain)
            ->post(route('dosen.mata-kuliah.materi.store', $course), $this->dataLink())
            ->assertForbidden();

        $this->assertDatabaseCount('materials', 0);
    }

    public function test_tautan_dengan_skema_berbahaya_ditolak(): void
    {
        $course = Course::factory()->create();

        $this->actingAs($course->lecturer)
            ->post(route('dosen.mata-kuliah.materi.store', $course), $this->dataLink([
                'external_url' => 'javascript:alert(1)',
            ]))
            ->assertSessionHasErrors('external_url');

        $this->assertDatabaseCount('materials', 0);
    }

    public function test_uploaded_by_selalu_diambil_dari_pengguna_login_bukan_dari_input(): void
    {
        $course = Course::factory()->create();
        $orangLain = User::factory()->dosen()->create();

        $this->actingAs($course->lecturer)
            ->post(route('dosen.mata-kuliah.materi.store', $course), $this->dataLink([
                'uploaded_by' => $orangLain->id,
            ]));

        $this->assertDatabaseHas('materials', [
            'title' => 'Slide Pertemuan 1',
            'uploaded_by' => $course->lecturer_id,
        ]);
        $this->assertDatabaseMissing('materials', ['uploaded_by' => $orangLain->id]);
    }

    public function test_mahasiswa_tidak_terdaftar_tidak_dapat_melihat_daftar_atau_mengunduh_materi(): void
    {
        Storage::fake('local');
        $course = Course::factory()->create();
        $material = Material::factory()->withStoredFile()->create(['course_id' => $course->id]);
        $orangLuar = User::factory()->mahasiswa()->create();

        $this->actingAs($orangLuar)
            ->get(route('mahasiswa.mata-kuliah.materi.index', $course))
            ->assertForbidden();

        $this->actingAs($orangLuar)
            ->get(route('materi.unduh', $material))
            ->assertForbidden();
    }

    public function test_mahasiswa_terdaftar_dapat_mengunduh_materi(): void
    {
        Storage::fake('local');
        $course = Course::factory()->create();
        $material = Material::factory()->withStoredFile()->create(['course_id' => $course->id]);
        $mahasiswa = User::factory()->mahasiswa()->create();
        $course->students()->attach($mahasiswa->id, ['enrolled_at' => now()]);

        $this->actingAs($mahasiswa)
            ->get(route('materi.unduh', $material))
            ->assertOk()
            ->assertDownload($material->original_name);
    }
}