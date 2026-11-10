<?php

namespace App\Notifications;

use App\Models\Assignment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Notifikasi database untuk mahasiswa: ada tugas baru di mata kuliahnya.
 *
 * Payload HANYA berisi jenis, ID, dan judul. Ini sesuai aturan nomor 8 dan
 * kontrak yang dibaca halaman Pusat Notifikasi mahasiswa.
 */
class TugasBaruNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** Jeda (detik) sebelum percobaan ke-2 dan ke-3. */
    public array $backoff = [10, 60];

    /** Tugas dihapus sebelum worker jalan: buang job, jangan dianggap gagal. */
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
            'jenis' => 'tugas_baru',
            'assignment_id' => $this->assignment->id,
            'judul' => $this->assignment->title,
        ];
    }

    public function failed(Throwable $e): void
    {
        Log::error('Notifikasi tugas baru gagal dikirim.', [
            'assignment_id' => $this->assignment->id,
            'error' => $e->getMessage(),
        ]);
    }
}