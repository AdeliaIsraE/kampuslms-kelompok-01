<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\Grade;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GradeTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Course, 1: Submission} */
    private function skenario(int $maxScore = 100): array
    {
        $course = Course::factory()->create();
        $assignment = Assignment::factory()->for($course)->create(['max_score' => $maxScore]);
        $mahasiswa = User::factory()->mahasiswa()->create();
        $course->students()->attach($mahasiswa->id, ['enrolled_at' => now()]);

        $submission = Submission::factory()->create([
            'assignment_id' => $assignment->id,
            'user_id' => $mahasiswa->id,
        ]);

        return [$course, $submission];
    }

    public function test_dosen_pengampu_dapat_memberi_nilai(): void
    {
        [$course, $submission] = $this->skenario();

        $this->actingAs($course->lecturer)
            ->put(route('dosen.pengumpulan.nilai', $submission), [
                'score' => 85,
                'feedback' => 'Sudah baik.',
            ])
            ->assertSessionHas('grade_success', 'Nilai berhasil disimpan.');

        $grade = Grade::where('submission_id', $submission->id)->firstOrFail();

        $this->assertSame('85.00', (string) $grade->score);
        $this->assertSame($course->lecturer_id, (int) $grade->graded_by);
    }

    public function test_memberi_nilai_dua_kali_memperbarui_bukan_menambah_baris(): void
    {
        [$course, $submission] = $this->skenario();

        $this->actingAs($course->lecturer)
            ->put(route('dosen.pengumpulan.nilai', $submission), ['score' => 70]);
        $this->actingAs($course->lecturer)
            ->put(route('dosen.pengumpulan.nilai', $submission), ['score' => 90])
            ->assertSessionHas('grade_success', 'Nilai berhasil diperbarui.');

        $this->assertSame(1, Grade::where('submission_id', $submission->id)->count());
        $this->assertSame('90.00', (string) Grade::first()->score);
    }

    public function test_dosen_lain_tidak_dapat_memberi_nilai(): void
    {
        [, $submission] = $this->skenario();
        $dosenLain = User::factory()->dosen()->create();

        $this->actingAs($dosenLain)
            ->put(route('dosen.pengumpulan.nilai', $submission), ['score' => 85])
            ->assertForbidden();

        $this->assertDatabaseCount('grades', 0);
    }

    public function test_admin_tidak_dapat_memberi_nilai(): void
    {
        [, $submission] = $this->skenario();
        $admin = User::factory()->admin()->create();

        // Route penilaian dijaga role:dosen, jadi admin ditolak sebelum policy.
        $this->actingAs($admin)
            ->put(route('dosen.pengumpulan.nilai', $submission), ['score' => 85])
            ->assertForbidden();

        $this->assertDatabaseCount('grades', 0);
    }

    public function test_mahasiswa_tidak_dapat_menilai_pengumpulannya_sendiri(): void
    {
        [, $submission] = $this->skenario();

        $this->actingAs($submission->user)
            ->put(route('dosen.pengumpulan.nilai', $submission), ['score' => 100])
            ->assertForbidden();

        $this->assertDatabaseCount('grades', 0);
    }

    public function test_nilai_di_luar_rentang_ditolak(): void
    {
        [$course, $submission] = $this->skenario(maxScore: 50);

        foreach ([-1, 51, 'abc'] as $skorTidakValid) {
            $this->actingAs($course->lecturer)
                ->put(route('dosen.pengumpulan.nilai', $submission), ['score' => $skorTidakValid])
                ->assertSessionHasErrors(['score'], null, 'grade');
        }

        $this->assertDatabaseCount('grades', 0);
    }

    public function test_nilai_yang_belum_terbit_tidak_tampil_di_rekap_mahasiswa(): void
    {
        [, $submission] = $this->skenario();
        Grade::factory()->create([
            'submission_id' => $submission->id,
            'feedback' => 'CATATAN-DRAF-RAHASIA',
            'is_published' => false,
        ]);

        $this->actingAs($submission->user)
            ->get(route('mahasiswa.nilai'))
            ->assertOk()
            ->assertDontSee('CATATAN-DRAF-RAHASIA');
    }

    public function test_nilai_yang_sudah_terbit_tampil_di_rekap_mahasiswa(): void
    {
        [, $submission] = $this->skenario();
        Grade::factory()->published()->create([
            'submission_id' => $submission->id,
            'feedback' => 'CATATAN-TERBIT-OK',
        ]);

        $this->actingAs($submission->user)
            ->get(route('mahasiswa.nilai'))
            ->assertOk()
            ->assertSee('CATATAN-TERBIT-OK');
    }
}