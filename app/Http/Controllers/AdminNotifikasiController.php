<?php

namespace App\Http\Controllers;

use App\Services\AdminNotifikasiService;
use App\Support\FormatNotifikasi;
use App\Support\JumlahNotifikasi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

/**
 * Pusat Notifikasi admin.
 *
 * Controller ini hanya membaca filter dari request, memanggil
 * AdminNotifikasiService, lalu mengirim hasilnya ke view. Query, agregasi,
 * dan pembentukan item feed ada di service.
 */
class AdminNotifikasiController extends Controller
{
    public function __construct(private readonly AdminNotifikasiService $notifikasi)
    {
    }

    public function index(Request $request): View
    {
        $admin = $request->user();

        // Filter dari URL (nilai di luar daftar diabaikan)
        $tab = $request->query('tab');
        $tab = is_string($tab) && isset(AdminNotifikasiService::TAB[$tab]) ? $tab : 'semua';

        $periode = $request->query('periode');
        $periode = is_string($periode) && isset(FormatNotifikasi::PERIODE[$periode]) ? $periode : '30-hari';

        $q = $request->query('q');
        $q = is_string($q) ? mb_substr(trim($q), 0, 100) : '';

        $feed = $this->notifikasi->feed($admin, $tab, $periode, $q, (int) $request->query('page', 1));

        $halaman = new LengthAwarePaginator(
            $feed['items'],
            $feed['total'],
            AdminNotifikasiService::PER_PAGE,
            $feed['halamanAktif'],
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('admin.notifikasi', [
            'ringkasan' => $this->notifikasi->ringkasan(),
            'mkAktif' => $this->notifikasi->mataKuliahAktif(),
            'tenggat' => $this->notifikasi->tenggatTerdekat(),
            'tab' => $tab,
            'tabs' => $feed['tabs'],
            'periode' => $periode,
            'opsiPeriode' => FormatNotifikasi::PERIODE,
            'q' => $q,
            'grupHari' => FormatNotifikasi::kelompokkanPerHari($feed['items']),
            'halaman' => $halaman,
            'total' => $feed['total'],
            'belumDibaca' => JumlahNotifikasi::adminBelumDibaca($admin),
        ]);
    }

    /** POST admin/notifikasi/dibaca-semua */
    public function dibacaSemua(Request $request): RedirectResponse
    {
        $jumlah = $this->notifikasi->tandaiSemuaDibaca($request->user());

        // back() mempertahankan tab, periode, pencarian, dan halaman yang sedang dibuka.
        return back()->with(
            'success',
            $jumlah > 0 ? "{$jumlah} aktivitas ditandai sudah dibaca." : 'Tidak ada aktivitas yang belum dibaca.'
        );
    }

    /** POST admin/notifikasi/{jenis}/{id}/dibaca */
    public function dibaca(Request $request, string $jenis, int $id): RedirectResponse
    {
        $this->notifikasi->tandaiDibaca($request->user(), $jenis, $id);

        // back() mempertahankan tab, periode, pencarian, dan halaman yang sedang dibuka.
        return back()->with('success', 'Aktivitas ditandai sudah dibaca.');
    }
}