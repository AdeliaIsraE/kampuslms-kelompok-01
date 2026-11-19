<?php

namespace App\Http\Controllers;

use App\Services\MahasiswaNotifikasiService;
use App\Support\FormatNotifikasi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

/**
 * Pusat Notifikasi mahasiswa.
 *
 * Controller ini hanya membaca filter dari request, memanggil
 * MahasiswaNotifikasiService, lalu mengirim hasilnya ke view. Daftar utamanya
 * adalah notifikasi database bawaan Laravel ($user->notifications()); query
 * dan pembentukan item ada di service.
 */
class MahasiswaNotifikasiController extends Controller
{
    public function __construct(private readonly MahasiswaNotifikasiService $notifikasi)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();

        // Filter dari URL (nilai di luar daftar diabaikan)
        $tab = $request->query('tab');
        $tab = is_string($tab) && isset(MahasiswaNotifikasiService::TAB[$tab]) ? $tab : 'semua';

        $periode = $request->query('periode');
        $periode = is_string($periode) && isset(FormatNotifikasi::PERIODE[$periode]) ? $periode : 'semua';

        $halamanAktif = LengthAwarePaginator::resolveCurrentPage();

        $feed = $this->notifikasi->feed($user, $tab, $periode, $halamanAktif);

        $halaman = (new LengthAwarePaginator(
            $feed['items'],
            $feed['total'],
            MahasiswaNotifikasiService::PER_PAGE,
            $halamanAktif,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'pageName' => 'page',
            ]
        ))->withQueryString();

        return view('mahasiswa.notifikasi', [
            'ringkasan' => $this->notifikasi->ringkasan($user),
            'tenggat' => $this->notifikasi->tenggatTerdekat($user),
            'progres' => $this->notifikasi->progresPengumpulan($user),
            'tab' => $tab,
            'tabs' => $feed['tabs'],
            'periode' => $periode,
            'opsiPeriode' => FormatNotifikasi::PERIODE,
            'grupHari' => FormatNotifikasi::kelompokkanPerHari($halaman->getCollection()),
            'halaman' => $halaman,
            'total' => $halaman->total(),
        ]);
    }

    /** POST mahasiswa/notifikasi/{id}/dibaca */
    public function dibaca(Request $request, string $id): RedirectResponse
    {
        $this->notifikasi->tandaiDibaca($request->user(), $id);

        // back() mempertahankan tab, periode, dan halaman yang sedang dibuka.
        return back();
    }

    /** POST mahasiswa/notifikasi/dibaca-semua */
    public function dibacaSemua(Request $request): RedirectResponse
    {
        $jumlah = $this->notifikasi->tandaiSemuaDibaca($request->user());

        return back()->with(
            'success',
            $jumlah > 0 ? "{$jumlah} notifikasi ditandai sudah dibaca." : 'Tidak ada notifikasi yang belum dibaca.'
        );
    }
}