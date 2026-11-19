<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Pembantu tampilan yang dipakai bersama oleh Pusat Notifikasi admin, dosen,
 * dan mahasiswa (filter periode, label hari, ukuran berkas, sisa waktu).
 * Sebelumnya potongan yang sama ditulis ulang di tiap controller.
 */
class FormatNotifikasi
{
    /** Pilihan filter periode di halaman notifikasi. */
    public const PERIODE = [
        'hari-ini' => 'Hari ini',
        '7-hari' => '7 hari terakhir',
        '30-hari' => '30 hari terakhir',
        'semua' => 'Semua waktu',
    ];

    /** Batas awal waktu untuk sebuah periode; null berarti semua waktu. */
    public static function sejak(string $periode): ?Carbon
    {
        return match ($periode) {
            'hari-ini' => now()->startOfDay(),
            '7-hari' => now()->subDays(6)->startOfDay(),
            '30-hari' => now()->subDays(29)->startOfDay(),
            default => null,
        };
    }

    /** Label kelompok hari: "Hari Ini — 19 November 2026", "Kemarin — ...", atau nama hari. */
    public static function labelHari(Carbon $tgl): string
    {
        $lokal = $tgl->copy()->locale('id');
        $tanggal = $lokal->translatedFormat('j F Y');

        return match (true) {
            $tgl->isToday() => "Hari Ini — {$tanggal}",
            $tgl->isYesterday() => "Kemarin — {$tanggal}",
            default => $lokal->translatedFormat('l') . ", {$tanggal}",
        };
    }

    /**
     * Kelompokkan item feed (setiap item punya kunci 'at' bertipe Carbon)
     * per hari untuk ditampilkan di view.
     *
     * @param  Collection<int, array>  $items
     */
    public static function kelompokkanPerHari(Collection $items): Collection
    {
        return $items
            ->groupBy(fn (array $i) => $i['at']->toDateString())
            ->map(fn (Collection $isi) => [
                'label' => self::labelHari($isi->first()['at']),
                'jumlah' => $isi->count(),
                'items' => $isi->all(),
            ])
            ->values();
    }

    /** Ukuran berkas untuk kartu notifikasi, mis. "850 B", "12 KB", "1,5 MB". */
    public static function ukuran(int $byte): string
    {
        if ($byte >= 1048576) {
            return number_format($byte / 1048576, 1, ',', '.') . ' MB';
        }

        if ($byte >= 1024) {
            return number_format($byte / 1024, 0, ',', '.') . ' KB';
        }

        return $byte . ' B';
    }

    /**
     * Tanggal, jam, dan sisa waktu menuju batas pengumpulan.
     *
     * @return array{tanggal: string, jam: string, sisa: string, mendesak: bool}
     */
    public static function tenggat(mixed $dueAt): array
    {
        $due = Carbon::parse($dueAt)->locale('id');
        $detik = max(0, $due->getTimestamp() - now()->getTimestamp());
        $hari = intdiv($detik, 86400);
        $jam = intdiv($detik % 86400, 3600);
        $menit = intdiv($detik % 3600, 60);

        return [
            'tanggal' => $due->translatedFormat('j M'),
            'jam' => $due->format('H:i'),
            'sisa' => $hari > 0
                ? "Sisa {$hari} hari {$jam} jam"
                : "Sisa {$jam} jam {$menit} menit",
            'mendesak' => $detik < 86400,
        ];
    }

    /** Tingkat capaian untuk warna bar progres: tinggi (>=75), sedang (>=50), rendah. */
    public static function level(int $persen): string
    {
        return $persen >= 75 ? 'tinggi' : ($persen >= 50 ? 'sedang' : 'rendah');
    }
}