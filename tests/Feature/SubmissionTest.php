<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SubmissionTest extends TestCase
{
    use RefreshDatabase;

    /** Mata kuliah + tugas + mahasiswa yang SUDAH terdaftar. */
    private function skenario(array $tugas = []): array
    {
        $course = Course::factory()->create();
        $assignment = Assignment::factory()->for($course)->create($tugas);
        $mahasiswa = User::factory()->mahasiswa()->create();
        $course->students()->attach($mahasiswa->id, ['enrolled_at' => now()]);

        return [$course, $assignment, $mahasiswa];
    }

    private function berkas(): UploadedFile
    {
        return UploadedFile::fake()->create('tugas.pdf', 200, 'application/pdf');
    }

    public function test_mahasiswa_terdaftar_bisa_mengumpulkan_tugas(): void
    {
        Storage::fake('local');
        [, $assignment, $mahasiswa] = $this->skenario();

        $this->actingAs($mahasiswa)
            ->post(route('mahasiswa.tugas.kumpul', $assignment), ['file' => $this->berkas()])
            ->assertRedirect();

        $submission = Submission::where('assignment_id', $assignment->id)
            ->where('user_id', $mahasiswa->id)
            ->firstOrFail();

        Storage::disk('local')->assertExists($submission->file_path);
        $this->assertFalse((bool) $submission->is_late);
    }

    public function test_pengumpulan_ditolak_setelah_deadline_bila_allow_late_false(): void
    {
        Storage::fake('local');
        [, $assignment, $mahasiswa] = $this->skenario([
            'due_at' => now()->subDay(),
            'allow_late' => false,
        ]);

        $this->actingAs($mahasiswa)
            ->post(route('mahasiswa.tugas.kumpul', $assignment), ['file' => $this->berkas()])
            ->assertForbidden();

        $this->assertDatabaseCount('submissions', 0);
    }

    public function test_pengumpulan_ditolak_untuk_mahasiswa_yang_tidak_terdaftar(): void
    {
        Storage::fake('local');
        [, $assignment] = $this->skenario();
        $orangLuar = User::factory()->mahasiswa()->create();

        $this->actingAs($orangLuar)
            ->post(route('mahasiswa.tugas.kumpul', $assignment), ['file' => $this->berkas()])
            ->assertForbidden();

        $this->assertDatabaseCount('submissions', 0);
    }

    public function test_mahasiswa_tidak_bisa_mengunduh_pengumpulan_orang_lain(): void
    {
        Storage::fake('local');
        [$course, $assignment, $budi] = $this->skenario();
        $ani = User::factory()->mahasiswa()->create();
        $course->students()->attach($ani->id, ['enrolled_at' => now()]);

        $milikAni = Submission::factory()->withStoredFile()->create([
            'assignment_id' => $assignment->id,
            'user_id' => $ani->id,
        ]);

        $this->actingAs($budi)
            ->get(route('pengumpulan.unduh', $milikAni))
            ->assertForbidden();
    }

    public function test_pemilik_bisa_mengunduh_pengumpulannya_sendiri(): void
    {
        Storage::fake('local');
        [, $assignment, $mahasiswa] = $this->skenario();

        $milikSendiri = Submission::factory()->withStoredFile()->create([
            'assignment_id' => $assignment->id,
            'user_id' => $mahasiswa->id,
        ]);

        $this->actingAs($mahasiswa)
            ->get(route('pengumpulan.unduh', $milikSendiri))
            ->assertOk()
            ->assertDownload($milikSendiri->original_name);
    }
}