<?php

namespace App\Events;

use App\Models\Assignment;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Sebuah tugas baru saja berstatus "published" (dibuat langsung terbit, atau
 * berubah dari draft ke published). Dipicu dari Assignment::booted(), jadi
 * berlaku sama untuk web, API, dan tinker.
 *
 * ShouldDispatchAfterCommit: bila terjadi di dalam transaksi, event baru
 * dikirim setelah commit, sehingga worker tidak membaca data yang belum tersimpan.
 */
class TugasDipublikasikan implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public Assignment $assignment)
    {
    }
}