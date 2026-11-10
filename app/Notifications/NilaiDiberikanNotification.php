<?php

namespace App\Notifications;

use App\Models\Assignment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Notifikasi database untuk mahasiswa: nilai tugasnya sudah tersedia.
 *
 * Sengaja hanya menerima Assignment (bukan Grade), sehingga nilai dan feedback
 * tidak mungkin ikut tersimpan di payload (aturan nomor 8). Mahasiswa membuka
 * tugasnya untuk melihat nilai, dan akses itu dijaga policy.
 */
class NilaiDiberikanNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [10, 60];

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Assignment $assignment)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'jenis' => 'nilai',
            'assignment_id' => $this->assignment->id,
            'judul' => $this->assignment->title,
        ];
    }

    public function failed(Throwable $e): void
    {
        Log::error('Notifikasi nilai gagal dikirim.', [
            'assignment_id' => $this->assignment->id,
            'error' => $e->getMessage(),
        ]);
    }
}