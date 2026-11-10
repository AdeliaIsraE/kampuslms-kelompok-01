<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penanda "semua notifikasi sampai waktu ini sudah dibaca".
     *
     * Dipakai Pusat Notifikasi admin, yang feed-nya diturunkan dari tabel lain
     * (tidak ada tabel notifications), jadi status dibaca cukup satu timestamp
     * per user: aktivitas dengan waktu <= kolom ini dianggap sudah dibaca.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('notifications_read_at')->nullable()->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('notifications_read_at');
        });
    }
};