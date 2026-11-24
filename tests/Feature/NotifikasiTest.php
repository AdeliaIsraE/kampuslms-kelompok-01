<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\Submission;
use App\Models\User;
use App\Notifications\NilaiDiberikanNotification;
use App\Notifications\TugasBaruNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Antrean memakai QUEUE_CONNECTION=sync (phpunit.xml), jadi listener langsung
 * berjalan. Jangan pakai Queue::fake() di sini: listener-nya ikut tertahan.
 */
class NotifikasiTest extends TestCase
{
    use RefreshDatabase;

    private function dataTugas(string $status): array
    {
        return [
            'title' => 'Tugas Notifikasi',
            'instructions' => 'Kerjakan.',
            'due_at' => now()->addDays(3)->format('Y-m-d H:i:s'),
            'max_score' => 100,
            'allow_late' => 1,
            'status' => $status,
        ];
    }

    /** @return array{0: Course, 1: User} course dengan satu mahasiswa terdaftar */
    private function courseDenganMahasiswa(): array
    {
        $course = Course::factory()->create();
        $mahasiswa = User::factory()->mahasiswa()->create();
        $course->students()->attach($mahasiswa->id, ['enrolled_at' => now()]);

        return [$course, $mahasiswa];
    }

    public function test_tugas_published_mengirim_notifikasi_ke_mahasiswa_terdaftar_saja(): void
    {
        [$course, $terdaftar] = $this->courseDenganMahasiswa();
        $orangLuar = User::factory()->mahasiswa()->create();
        Notification::fake();

        $this->actingAs($course->lecturer)
            ->post(route('dosen.mata-kuliah.tugas.store', $course), $this->dataTugas('published'))
            ->assertRedirect();

        Notification::assertSentTo($terdaftar, TugasBaruNotification::class);
        Notification::assertNotSentTo($orangLuar, TugasBaruNotification::class);
    }

    public function test_tugas_draft_tidak_mengirim_notifikasi(): void
    {
        [$course] = $this->courseDenganMahasiswa();
        Notification::fake();

        $this->actingAs($course->lecturer)
            ->post(route('dosen.mata-kuliah.tugas.store', $course), $this->dataTugas('draft'))
            ->assertRedirect();

        Notification::assertNothingSent();
    }

    public function test_menerbitkan_nilai_mengirim_notifikasi_ke_pemilik_pengumpulan(): void
    {
        [$course, $mahasiswa] = $this->courseDenganMahasiswa();
        $submission = Submission::factory()->create([
            'assignment_id' => Assignment::factory()->for($course)->create()->id,
            'user_id' => $mahasiswa->id,
        ]);
        Notification::fake();

        $this->actingAs($course->lecturer)
            ->put(route('dosen.pengumpulan.nilai', $submission), [
                'score' => 90,
                'is_published' => true,
            ]);

        Notification::assertSentTo($mahasiswa, NilaiDiberikanNotification::class);
    }

    public function test_nilai_yang_belum_diterbitkan_tidak_mengirim_notifikasi(): void
    {
        [$course, $mahasiswa] = $this->courseDenganMahasiswa();
        $submission = Submission::factory()->create([
            'assignment_id' => Assignment::factory()->for($course)->create()->id,
            'user_id' => $mahasiswa->id,
        ]);
        Notification::fake();

        $this->actingAs($course->lecturer)
            ->put(route('dosen.pengumpulan.nilai', $submission), [
                'score' => 90,
                'is_published' => false,
            ]);

        Notification::assertNothingSent();
    }

    public function test_mengubah_skor_nilai_yang_sudah_terbit_tidak_mengirim_notifikasi_ganda(): void
    {
        [$course, $mahasiswa] = $this->courseDenganMahasiswa();
        $submission = Submission::factory()->create([
            'assignment_id' => Assignment::factory()->for($course)->create()->id,
            'user_id' => $mahasiswa->id,
        ]);
        Notification::fake();

        $this->actingAs($course->lecturer)
            ->put(route('dosen.pengumpulan.nilai', $submission), ['score' => 80, 'is_published' => true]);
        $this->actingAs($course->lecturer)
            ->put(route('dosen.pengumpulan.nilai', $submission), ['score' => 95, 'is_published' => true]);

        Notification::assertSentToTimes($mahasiswa, NilaiDiberikanNotification::class, 1);
    }
}