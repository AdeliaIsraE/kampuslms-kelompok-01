<?php

namespace App\Http\Controllers;

use App\Services\DosenNotifikasiService;
use App\Support\FormatNotifikasi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

/**
 * Pusat Notifikasi dosen.
 *
 * Controller ini hanya membaca filter dari request, memanggil
 * DosenNotifikasiService, lalu mengirim hasilnya ke view. Query, agregasi,
 * dan pembentukan item ada di service.
 */
class DosenNotifikasiController extends Controller
{
    public function __construct(private readonly DosenNotifikasiService $notifikasi)
    {
    }

    public function index(Request $request): View
    {
        $dosen = $request->user();

        $mataKuliah = $this->notifikasi->mataKuliah($dosen);
        $mkIds = $this->notifikasi->idMataKuliah($mataKuliah);

        // Filter dari URL (nilai di luar daftar diabaikan)
        $tab = $request->query('tab');
        $tab = is_string($tab) && isset(DosenNotifikasiService::TAB[$tab]) ? $tab : 'semua';

        $periode = $request->query('periode');
        $periode = is_string($periode) && isset(FormatNotifikasi::PERIODE[$periode]) ? $periode : '30-hari';

        $q = $request->query('q');
        $q = is_string($q) ? mb_substr(trim($q), 0, 100) : '';

        $mk = $request->query('mk');
        $mk = is_string($mk) && ctype_digit($mk) && in_array((int) $mk, $mkIds, true) ? (int) $mk : null;

        $halamanAktif = LengthAwarePaginator::resolveCurrentPage();

        $feed = $this->notifikasi->feed($dosen, $mkIds, $tab, $periode, $q, $mk, $halamanAktif);

        $halaman = (new LengthAwarePaginator(
            $feed['items'],
            $feed['total'],
            DosenNotifikasiService::PER_PAGE,
            $halamanAktif,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'pageName' => 'page',
            ]
        ))->withQueryString();

        return view('dosen.notifikasi', [
            'ringkasan' => $this->notifikasi->ringkasan($mkIds),
            'antrean' => $this->notifikasi->antreanPenilaian($mataKuliah, $mkIds),
            'tenggat' => $this->notifikasi->tenggatTerdekat($mkIds),
            'mataKuliah' => $mataKuliah,
            'tab' => $tab,
            'tabs' => $feed['tabs'],
            'periode' => $periode,
            'opsiPeriode' => FormatNotifikasi::PERIODE,
            'q' => $q,
            'mk' => $mk,
            'grupHari' => FormatNotifikasi::kelompokkanPerHari($halaman->getCollection()),
            'halaman' => $halaman,
            'total' => $halaman->total(),
            'belumDibaca' => $this->notifikasi->belumDibaca($dosen, $mkIds),
        ]);
    }

    /** POST dosen/notifikasi/{id}/dibaca */
    public function dibaca(Request $request, int $id): RedirectResponse
    {
        $this->notifikasi->tandaiDibaca($request->user(), $id);

        // back() mempertahankan tab, filter, dan halaman yang sedang dibuka.
        return back()->with('success', 'Pengumpulan ditandai sudah dibaca.');
    }

    /** POST dosen/notifikasi/dibaca-semua */
    public function dibacaSemua(Request $request): RedirectResponse
    {
        $jumlah = $this->notifikasi->tandaiSemuaDibaca($request->user());

        return back()->with(
            'success',
            $jumlah > 0 ? "{$jumlah} pengumpulan ditandai sudah dibaca." : 'Tidak ada pengumpulan yang belum dibaca.'
        );
    }
}