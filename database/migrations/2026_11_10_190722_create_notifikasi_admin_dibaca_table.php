<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aktivitas yang sudah ditandai dibaca satu per satu oleh admin di Pusat
     * Notifikasi. Feed admin diturunkan dari tabel materials, assignments, dan
     * submissions (tidak ada tabel notifications), jadi penanda ini disimpan
     * terpisah: satu baris = satu aktivitas yang disembunyikan untuk satu admin.
     *
     * jenis   : 'materi' | 'tugas' | 'pengumpulan'
     * item_id : id di tabel sumbernya (materials / assignments / submissions)
     */
    public function up(): void
    {
        Schema::create('notifikasi_admin_dibaca', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('jenis', 20);
            $table->unsignedBigInteger('item_id');
            $table->timestamp('created_at')->nullable();

            $table->unique(['user_id', 'jenis', 'item_id'], 'notif_admin_dibaca_unik');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifikasi_admin_dibaca');
    }
};