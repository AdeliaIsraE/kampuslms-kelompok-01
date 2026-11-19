<?php

namespace App\Support;

use App\Models\Assignment;
use App\Models\Material;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Angka pada lonceng notifikasi di navbar. Satu sumber untuk dua jalur:
 * angka awal yang dirender server di komponen notification-bell, dan jawaban
 * endpoint polling (NotifikasiController@belumDibaca), jadi keduanya selalu sama.
 *
 * - admin     : aktivitas administratif (materi diunggah + tugas terbit) yang belum
 *               ditandai dibaca, satu per satu atau sekaligus (users.notifications_read_at).
 *               Pengumpulan tugas mahasiswa TIDAK termasuk: itu aktivitas operasional per
 *               kelas yang ditangani dosen pengampu (lihat role dosen di bawah).
 * - dosen     : pengumpulan di mata kuliah yang diampu yang belum dinilai dan belum
 *               ditandai dibaca (users.notifications_read_at + notifikasi_admin_dibaca)
 * - mahasiswa : notifikasi database yang belum dibaca (tabel notifications)
 */
class JumlahNotifikasi
{
    public static function untuk(User $user): int
    {
        return match ($user->role) {
            'admin' => self::adminBelumDibaca($user),
            'dosen' => self::menungguNilai($user),
            default => $user->unreadNotifications()->count(),
        };
    }

    /**
     * Batas "sudah dibaca" untuk admin: aktivitas dengan waktu <= batas ini
     * sudah ditandai dibaca lewat "Tandai semua dibaca" dan tidak ditampilkan
     * lagi. null berarti belum pernah ditandai, jadi semua aktivitas tampil.
     */
    public static function batasDibacaAdmin(User $admin): ?Carbon
    {
        return $admin->notifications_read_at;
    }

    /**
     * Waktu yang disimpan saat admin menekan "Tandai semua dibaca": SEKARANG.
     *
     * Sengaja tidak memakai waktu aktivitas terbaru di database: bila batas
     * digeser ke timestamp yang lebih baru dari sekarang, aktivitas yang masuk
     * sesudahnya (waktunya < batas) tidak akan tampil sampai waktu itu lewat.
     * Dengan now(), hanya aktivitas yang sudah ada saat ini yang disembunyikan,
     * dan yang masuk sesudahnya tetap muncul sebagai baru.
     *
     * Syarat: tidak ada aktivitas bertanggal di masa depan. DatabaseSeeder
     * membatasi submitted_at maksimal sekarang agar syarat ini terpenuhi.
     */
    public static function batasTandaiSemuaAdmin(): Carbon
    {
        return now();
    }

    /**
     * Buang aktivitas yang sudah ditandai dibaca satu per satu oleh admin ini
     * (tabel notifikasi_admin_dibaca). Dipakai feed, hitungan tab, dan lonceng
     * supaya ketiganya selalu sepakat.
     *
     * @param  string  $jenis    'materi' | 'tugas' | 'pengumpulan'
     * @param  string  $kolomId  kolom id sumbernya, mis. 'materials.id'
     */
    public static function tanpaDitandai(Builder $query, int $adminId, string $jenis, string $kolomId): Builder
    {
        return $query->whereNotExists(function ($w) use ($adminId, $jenis, $kolomId) {
            $w->selectRaw('1')
                ->from('notifikasi_admin_dibaca as nd')
                ->where('nd.user_id', $adminId)
                ->where('nd.jenis', $jenis)
                ->whereColumn('nd.item_id', $kolomId);
        });
    }

    /**
     * Semua aktivitas yang masih tampil di Pusat Notifikasi admin (belum ditandai dibaca), dalam SATU query.
     *
     * Hanya materi dan tugas terbit. Tabel submissions sengaja tidak disentuh: angka ini
     * dihitung setiap 30 detik per admin (polling lonceng), dan pengumpulan mahasiswa se-kampus
     * jumlahnya besar serta membuat angka lonceng selalu penuh.
     */
    public static function adminBelumDibaca(User $admin): int
    {
        $batas = self::batasDibacaAdmin($admin);

        $query = DB::query()
            ->selectSub(
                self::tanpaDitandai(Material::query()->when($batas, fn ($q) => $q->where('created_at', '>', $batas)), $admin->id, 'materi', 'materials.id')->selectRaw('count(*)'),
                'aktivitas_materi'
            )
            ->selectSub(
                self::tanpaDitandai(Assignment::where('status', 'published')->when($batas, fn ($q) => $q->where('created_at', '>', $batas)), $admin->id, 'tugas', 'assignments.id')->selectRaw('count(*)'),
                'aktivitas_tugas'
            );

        return self::jumlahkan($query->first());
    }

    /**
     * Jumlah aktivitas hari ini (materi + tugas terbit) dalam SATU query
     * (2 subquery scalar), bukan 2 query terpisah.
     */
    public static function aktivitasHariIni(): int
    {
        $query = DB::query();

        foreach (self::subkueriAktivitasHariIni() as $nama => $sub) {
            $query->selectSub($sub, $nama);
        }

        return self::jumlahkan($query->first());
    }

    /**
     * Dua hitungan aktivitas hari ini, dalam bentuk subquery yang bisa
     * ditempelkan ke query lain lewat selectSub(). Dipakai juga oleh
     * AdminDashboardController supaya definisi "aktivitas hari ini" hanya
     * ada di satu tempat.
     *
     * @return array<string, Builder>
     */
    public static function subkueriAktivitasHariIni(): array
    {
        $hariIni = now()->startOfDay();

        return [
            'aktivitas_materi'      => Material::where('created_at', '>=', $hariIni)->selectRaw('count(*)'),
            'aktivitas_tugas'       => Assignment::where('status', 'published')
                                        ->where('created_at', '>=', $hariIni)->selectRaw('count(*)'),
        ];
    }

    /** Menjumlahkan dua kolom aktivitas_* dari satu baris hasil query. */
    public static function jumlahkan(object $baris): int
    {
        return (int) $baris->aktivitas_materi
            + (int) $baris->aktivitas_tugas;
    }

    /**
     * Pengumpulan di mata kuliah dosen yang belum dinilai DAN masih tampil di
     * Pusat Notifikasi (belum ditandai dibaca, satu per satu atau sekaligus).
     * Kartu "Perlu Dinilai" di halaman itu tetap menghitung semuanya.
     */
    private static function menungguNilai(User $dosen): int
    {
        $batas = $dosen->notifications_read_at;

        return self::tanpaDitandai(
            Submission::query()
                ->join('assignments', 'assignments.id', '=', 'submissions.assignment_id')
                ->join('courses', 'courses.id', '=', 'assignments.course_id')
                ->where('courses.lecturer_id', $dosen->id)
                ->whereDoesntHave('grade')
                ->when($batas, fn ($q) => $q->where('submissions.submitted_at', '>', $batas)),
            $dosen->id,
            'pengumpulan',
            'submissions.id'
        )->count();
    }

    /**
     * Waktu yang disimpan saat dosen menekan "Tandai semua dibaca": SEKARANG.
     * Alasannya sama dengan batasTandaiSemuaAdmin(): jangan menggeser batas
     * ke waktu yang lebih baru dari sekarang, agar pengumpulan baru tetap
     * tampil. Parameter $dosen dipertahankan agar pemanggil tidak berubah.
     */
    public static function batasTandaiSemuaDosen(User $dosen): Carbon
    {
        return now();
    }
}