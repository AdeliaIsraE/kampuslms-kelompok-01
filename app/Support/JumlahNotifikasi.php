<?php

namespace App\Support;

use App\Models\Assignment;
use App\Models\Material;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Angka pada lonceng notifikasi di navbar. Satu sumber untuk dua jalur:
 * angka awal yang dirender server di komponen notification-bell, dan jawaban
 * endpoint polling (NotifikasiController@belumDibaca), jadi keduanya selalu sama.
 *
 * - admin     : aktivitas hari ini (materi diunggah + tugas terbit + pengumpulan masuk)
 * - dosen     : pengumpulan di mata kuliah yang diampu yang belum dinilai
 * - mahasiswa : notifikasi database yang belum dibaca (tabel notifications)
 */
class JumlahNotifikasi
{
    public static function untuk(User $user): int
    {
        return match ($user->role) {
            'admin' => self::aktivitasHariIni(),
            'dosen' => self::menungguNilai($user),
            default => $user->unreadNotifications()->count(),
        };
    }

    /**
     * Jumlah aktivitas hari ini dalam SATU query (3 subquery scalar),
     * bukan 3 query terpisah.
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
     * Tiga hitungan aktivitas hari ini, dalam bentuk subquery yang bisa
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
            'aktivitas_pengumpulan' => Submission::where('submitted_at', '>=', $hariIni)->selectRaw('count(*)'),
        ];
    }

    /** Menjumlahkan tiga kolom aktivitas_* dari satu baris hasil query. */
    public static function jumlahkan(object $baris): int
    {
        return (int) $baris->aktivitas_materi
            + (int) $baris->aktivitas_tugas
            + (int) $baris->aktivitas_pengumpulan;
    }

    /** Sama dengan kartu "Menunggu Penilaian" di halaman Notifikasi dosen. */
    private static function menungguNilai(User $dosen): int
    {
        return Submission::query()
            ->join('assignments', 'assignments.id', '=', 'submissions.assignment_id')
            ->join('courses', 'courses.id', '=', 'assignments.course_id')
            ->where('courses.lecturer_id', $dosen->id)
            ->whereDoesntHave('grade')
            ->count();
    }
}