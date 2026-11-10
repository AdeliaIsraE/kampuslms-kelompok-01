<?php

namespace App\Events;

use App\Models\Grade;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Nilai sebuah pengumpulan baru saja menjadi terlihat oleh mahasiswa
 * (nilai baru yang langsung published, atau is_published berubah menjadi true).
 * Dipicu dari App\Actions\GradeSubmission, yang dipakai web maupun API.
 */
class NilaiDiberikan implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public Grade $grade)
    {
    }
}