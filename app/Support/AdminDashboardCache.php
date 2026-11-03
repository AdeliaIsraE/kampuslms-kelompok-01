<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AdminDashboardCache
{
    /**
     * Cache key dashboard admin berdasarkan tanggal.
     */
    public static function key(): string
    {
        return 'admin_dashboard_statistics_' . now()->toDateString();
    }

    /**
     * Menghapus cache dashboard admin.
     * Jika sedang dalam transaksi, penghapusan dilakukan setelah commit.
     */
    public static function forget(): void
    {
        $forgetCache = fn () => Cache::forget(self::key());

        if (DB::transactionLevel() > 0) {
            DB::afterCommit($forgetCache);
            return;
        }

        $forgetCache();
    }
}