<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Material;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;

/**
 * @extends Factory<Material>
 *
 * Default: materi bertipe 'file' (PDF) milik sebuah mata kuliah, diunggah
 * oleh dosen pengampunya. Berkas fisiknya TIDAK dibuat; pakai
 * withStoredFile() kalau test butuh berkas benar-benar ada di disk.
 *
 * Contoh:
 *   Material::factory()->for($course)->create();
 *   Material::factory()->link()->create(['course_id' => $course->id]);
 */
class MaterialFactory extends Factory
{
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            // uploaded_by wajib (NOT NULL): default-nya dosen pengampu MK materi ini.
            // Urutan kunci penting: harus setelah 'course_id'.
            'uploaded_by' => fn (array $attrs) => Course::findOrFail($attrs['course_id'])->lecturer_id,
            'title' => 'Materi ' . fake('id_ID')->words(3, true),
            'description' => fake('id_ID')->sentence(10),
            'type' => 'file',
            // Pola path sama dengan MaterialController: materials/{course_id}/{nama-acak}
            'file_path' => fn (array $attrs) => "materials/{$attrs['course_id']}/" . fake()->uuid() . '.pdf',
            'original_name' => 'materi_' . fake()->word() . '.pdf',
            'file_size' => fake()->numberBetween(50_000, 5_000_000),
            'mime_type' => 'application/pdf',
            'external_url' => null,
        ];
    }

    public function file(): static
    {
        return $this->state(fn () => ['type' => 'file']);
    }

    /** Materi tautan luar: kolom berkas dikosongkan, seperti MaterialController::prepareData. */
    public function link(): static
    {
        return $this->state(fn () => [
            'type' => 'link',
            'file_path' => null,
            'original_name' => null,
            'file_size' => null,
            'mime_type' => null,
            'external_url' => 'https://' . fake()->domainName() . '/materi',
        ]);
    }

    /**
     * Tulis berkas sungguhan ke disk 'local' setelah materi dibuat.
     * WAJIB didahului Storage::fake('local') di test, kalau tidak berkas
     * akan tertulis ke storage/app/private yang asli.
     */
    public function withStoredFile(): static
    {
        return $this->afterCreating(function (Material $material) {
            if ($material->file_path) {
                Storage::disk('local')->put($material->file_path, 'isi materi uji');
            }
        });
    }
}