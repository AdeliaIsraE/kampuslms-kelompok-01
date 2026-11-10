<?php

namespace App\Listeners;

use App\Events\TugasDipublikasikan;
use App\Notifications\TugasBaruNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Kirim notifikasi "tugas baru" ke semua mahasiswa yang terdaftar di mata kuliahnya.
 *
 * Listener ini masuk antrean (satu job), lalu Notification-nya juga masuk antrean
 * (satu job per mahasiswa): 1 + N job. Terdaftar otomatis lewat event discovery
 * karena handle() meng-type-hint event-nya (cek: php artisan event:list).
 */
class KirimNotifikasiTugasBaru implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;

    /** Jeda (detik) sebelum percobaan ke-2 dan ke-3. */
    public array $backoff = [10, 60];

    /** Tugas dihapus sebelum worker jalan: buang job, jangan dianggap gagal. */
    public bool $deleteWhenMissingModels = true;

    public function handle(TugasDipublikasikan $event): void
    {
        $assignment = $event->assignment;

        // Data dibaca ulang dari database saat job jalan: tugas bisa saja
        // dikembalikan ke draft sebelum worker sempat memprosesnya.
        if ($assignment->status !== 'published') {
            return;
        }

        $course = $assignment->course;

        // Mahasiswa hanya boleh membuka tugas dari mata kuliah yang active
        // (AssignmentPolicy::view), jadi notifikasinya pun hanya untuk itu.
        if (! $course || $course->status !== 'active') {
            return;
        }

        $mahasiswa = $course->students()->get();

        if ($mahasiswa->isEmpty()) {
            return;
        }

        Notification::send($mahasiswa, new TugasBaruNotification($assignment));
    }

    public function failed(TugasDipublikasikan $event, Throwable $e): void
    {
        Log::error('Listener notifikasi tugas baru gagal.', [
            'assignment_id' => $event->assignment->id,
            'error' => $e->getMessage(),
        ]);
    }
}