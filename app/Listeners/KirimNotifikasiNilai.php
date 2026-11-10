<?php

namespace App\Listeners;

use App\Events\NilaiDiberikan;
use App\Notifications\NilaiDiberikanNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Kirim notifikasi "nilai tersedia" ke mahasiswa pemilik pengumpulan.
 * Terdaftar otomatis lewat event discovery (cek: php artisan event:list).
 */
class KirimNotifikasiNilai implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;

    public array $backoff = [10, 60];

    /** Nilai atau pengumpulannya dihapus sebelum worker jalan: buang job. */
    public bool $deleteWhenMissingModels = true;

    public function handle(NilaiDiberikan $event): void
    {
        $grade = $event->grade;

        // Dibaca ulang saat job jalan: nilai bisa saja disembunyikan lagi sebelum diproses.
        if (! $grade->is_published) {
            return;
        }

        $submission = $grade->submission()->with(['assignment', 'student'])->first();

        if (! $submission || ! $submission->assignment || ! $submission->student) {
            return;
        }

        $submission->student->notify(new NilaiDiberikanNotification($submission->assignment));
    }

    public function failed(NilaiDiberikan $event, Throwable $e): void
    {
        Log::error('Listener notifikasi nilai gagal.', [
            'grade_id' => $event->grade->id,
            'error' => $e->getMessage(),
        ]);
    }
}