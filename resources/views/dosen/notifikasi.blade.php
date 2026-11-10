{{--
    Pusat Notifikasi dosen: pengumpulan tugas di mata kuliah yang diampu.
    Data dari DosenNotifikasiController. Gaya: resources/css/dashboard/notifikasi.css
    (dipakai bersama halaman notifikasi admin); kartu metrik memakai .admin-metric
    (components/dashboard.css) dan banner memakai .dash-hero (dashboard/dosen-mahasiswa.css).
--}}
@php
    // Parameter URL yang dipertahankan saat pindah tab (nilai bawaan tidak ditulis ke URL).
    $urlTab = fn (string $kunci) => route('dosen.notifikasi', array_filter([
        'tab' => $kunci === 'semua' ? null : $kunci,
        'q' => $q !== '' ? $q : null,
        'mk' => $mk,
        'periode' => $periode === '30-hari' ? null : $periode,
    ]));

    $filterAktif = $q !== '' || $mk !== null || $periode !== '30-hari';
@endphp

<x-layout title="Notifikasi Dosen" role="dosen" full-footer>
    {{-- Gaya tombol tandai dibaca. Bisa dipindah ke notifikasi.css. --}}
    <style>
        .notif-markall { display: flex; justify-content: flex-end; margin: 0 0 0.85rem; }
        .notif-inline-form { display: inline; margin: 0; }
    </style>

    <section class="notif">

        {{-- BANNER --}}
        <header class="dash-hero">
            <div class="dash-hero__top">
                <div>
                    <p class="dash-hero__portal">Portal Dosen</p>
                    <h1 class="dash-hero__title">Pusat Notifikasi</h1>
                </div>
            </div>
            <p class="dash-hero__sub">
                Pantau pengumpulan tugas mahasiswa dan antrean penilaian di mata kuliah yang kamu ampu.
            </p>
            <span class="dash-hero__status">
                <i></i>{{ $ringkasan['kumpulHariIni'] }} pengumpulan hari ini
            </span>
        </header>

        {{-- KARTU RINGKASAN --}}
        <section class="admin-metrics" aria-label="Ringkasan pengumpulan tugas">
            <article class="admin-metric admin-metric--orange">
                <div class="admin-metric__top">
                    <div class="admin-metric__icon">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <circle cx="12" cy="12" r="9"></circle>
                            <path d="M12 7v5l3 2"></path>
                        </svg>
                    </div>
                    <span class="admin-metric__index">01</span>
                </div>
                <div class="admin-metric__number">{{ $ringkasan['menunggu'] }}</div>
                <div class="admin-metric__label">Perlu Dinilai</div>
                <div class="admin-metric__caption">
                    Pengumpulan yang belum kamu beri nilai.
                    @if ($ringkasan['menungguTelat'] > 0)
                        {{ $ringkasan['menungguTelat'] }} di antaranya terlambat.
                    @endif
                </div>
            </article>

            <article class="admin-metric admin-metric--pink">
                <div class="admin-metric__top">
                    <div class="admin-metric__icon">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M16 11l2 2 4-4"></path>
                        </svg>
                    </div>
                    <span class="admin-metric__index">02</span>
                </div>
                <div class="admin-metric__number">{{ $ringkasan['kumpulHariIni'] }}</div>
                <div class="admin-metric__label">Pengumpulan Hari Ini</div>
                <div class="admin-metric__caption">
                    @if ($ringkasan['tepatHariIni'] !== null)
                        {{ $ringkasan['tepatHariIni'] }}% tepat waktu, {{ $ringkasan['telatHariIni'] }} terlambat.
                    @else
                        Belum ada pengumpulan hari ini.
                    @endif
                </div>
            </article>

            <article class="admin-metric admin-metric--blue">
                <div class="admin-metric__top">
                    <div class="admin-metric__icon">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <circle cx="12" cy="12" r="9"></circle>
                            <path d="M8 12.5l3 3 5-6"></path>
                        </svg>
                    </div>
                    <span class="admin-metric__index">03</span>
                </div>
                <div class="admin-metric__number">{{ $ringkasan['persenDinilai'] }}%</div>
                <div class="admin-metric__label">Progres Penilaian</div>
                <div class="admin-metric__caption">
                    {{ $ringkasan['dinilai'] }} dari {{ $ringkasan['total'] }} pengumpulan sudah dinilai.
                </div>
            </article>

            <article class="admin-metric admin-metric--purple">
                <div class="admin-metric__top">
                    <div class="admin-metric__icon">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <path d="M14 2v6h6"></path>
                            <path d="M8 13h8"></path>
                            <path d="M8 17h5"></path>
                        </svg>
                    </div>
                    <span class="admin-metric__index">04</span>
                </div>
                <div class="admin-metric__number">{{ $ringkasan['tugasAktif'] }}</div>
                <div class="admin-metric__label">Tugas Aktif</div>
                <div class="admin-metric__caption">
                    Tugas terbit yang belum lewat batas waktu, tersebar di {{ $ringkasan['tugasAktifMk'] }} mata kuliah.
                </div>
            </article>
        </section>

        <div class="notif-layout">

            {{-- KOLOM UTAMA: filter + feed --}}
            <div class="notif-main">

                <div class="notif-toolbar">
                    <nav class="notif-tabs" aria-label="Status pengumpulan">
                        @foreach ($tabs as $kunci => $info)
                            <a href="{{ $urlTab($kunci) }}"
                               class="notif-tab {{ $tab === $kunci ? 'is-active' : '' }}"
                               @if ($tab === $kunci) aria-current="page" @endif>
                                {{ $info['label'] }}
                                <span class="notif-tab__count">{{ $info['jumlah'] }}</span>
                            </a>
                        @endforeach
                    </nav>

                    <form method="GET" action="{{ route('dosen.notifikasi') }}" class="notif-filter">
                        <input type="hidden" name="tab" value="{{ $tab }}">

                        <label class="notif-search">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="11" cy="11" r="7"></circle>
                                <path d="M21 21l-4.3-4.3"></path>
                            </svg>
                            <input type="search" name="q" value="{{ $q }}" maxlength="100"
                                   placeholder="Cari mahasiswa, tugas, atau mata kuliah..." aria-label="Cari pengumpulan">
                        </label>

                        <select name="mk" class="notif-select" aria-label="Mata kuliah">
                            <option value="">Semua mata kuliah</option>
                            @foreach ($mataKuliah as $mkOpsi)
                                <option value="{{ $mkOpsi->id }}" @selected($mk === (int) $mkOpsi->id)>{{ $mkOpsi->code }} &middot; {{ $mkOpsi->name }}</option>
                            @endforeach
                        </select>

                        <select name="periode" class="notif-select" aria-label="Periode">
                            @foreach ($opsiPeriode as $kunci => $label)
                                <option value="{{ $kunci }}" @selected($periode === $kunci)>{{ $label }}</option>
                            @endforeach
                        </select>

                        <button type="submit" class="notif-btn notif-btn--primary">Terapkan</button>

                        @if ($filterAktif)
                            <a href="{{ route('dosen.notifikasi', array_filter(['tab' => $tab === 'semua' ? null : $tab])) }}"
                               class="notif-btn">Reset</a>
                        @endif
                    </form>
                </div>

                @if ($belumDibaca > 0)
                    <form method="POST" action="{{ route('dosen.notifikasi.dibaca-semua') }}" class="notif-markall">
                        @csrf
                        <button type="submit" class="notif-btn">Tandai semua dibaca ({{ $belumDibaca }})</button>
                    </form>
                @endif

                @forelse ($grupHari as $grup)
                    <section class="notif-day">
                        <div class="notif-day__head">
                            <h2>{{ $grup['label'] }}</h2>
                            <span>{{ $grup['jumlah'] }} pengumpulan</span>
                        </div>

                        <div class="notif-list">
                            @foreach ($grup['items'] as $item)
                                <article class="notif-item notif-item--{{ $item['nada'] }}">
                                    <div class="notif-item__icon" aria-hidden="true">
                                        @if ($item['nada'] === 'terlambat')
                                            <svg viewBox="0 0 24 24">
                                                <circle cx="12" cy="12" r="9"></circle>
                                                <path d="M12 7v5l3 2"></path>
                                            </svg>
                                        @else
                                            <svg viewBox="0 0 24 24">
                                                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                                                <circle cx="9" cy="7" r="4"></circle>
                                                <path d="M16 11l2 2 4-4"></path>
                                            </svg>
                                        @endif
                                    </div>

                                    <div class="notif-item__body">
                                        <div class="notif-item__top">
                                            <div class="notif-item__who">
                                                <strong>{{ $item['actor'] }}</strong>
                                                <span class="notif-chip notif-chip--muted">Mahasiswa</span>
                                                @foreach ($item['chips'] as [$teks, $varian])
                                                    <span class="notif-chip notif-chip--{{ $varian }}">{{ $teks }}</span>
                                                @endforeach
                                            </div>
                                            <time class="notif-item__time" datetime="{{ $item['at']->toIso8601String() }}" title="{{ $item['lengkap'] }}">
                                                {{ $item['relatif'] }}
                                            </time>
                                        </div>

                                        <p class="notif-item__text">
                                            mengumpulkan tugas
                                            <strong>"{{ $item['judul'] }}"</strong>
                                            pada mata kuliah
                                            <a href="{{ route('dosen.mata-kuliah.show', $item['course_id']) }}">{{ $item['course_name'] }}</a>.
                                        </p>

                                        <div class="notif-item__meta">
                                            <span class="notif-meta notif-meta--kode">{{ $item['course_code'] }}</span>
                                            @foreach ($item['meta'] as $meta)
                                                <span class="notif-meta">{{ $meta }}</span>
                                            @endforeach
                                        </div>

                                        <div class="notif-file">
                                            <span class="notif-file__icon" aria-hidden="true">
                                                <svg viewBox="0 0 24 24">
                                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                                    <path d="M14 2v6h6"></path>
                                                </svg>
                                            </span>
                                            <span class="notif-file__name">{{ $item['lampiran']['nama'] }}</span>
                                            <span class="notif-file__size">{{ $item['lampiran']['info'] }}</span>
                                        </div>

                                        @if ($item['catatan'])
                                            <p class="notif-note">{{ $item['catatan'] }}</p>
                                        @endif

                                        <div class="notif-item__actions">
                                            <a class="notif-btn notif-btn--primary" href="{{ route('dosen.tugas.show', $item['assignment_id']) }}">
                                                {{ $item['dinilai'] ? 'Periksa Nilai' : 'Beri Nilai' }}
                                            </a>
                                            <a class="notif-btn" href="{{ route('pengumpulan.unduh', $item['id']) }}">Unduh Berkas</a>
                                            <form method="POST" action="{{ route('dosen.notifikasi.dibaca', $item['id']) }}" class="notif-inline-form">
                                                @csrf
                                                <button type="submit" class="notif-btn">Tandai Dibaca</button>
                                            </form>
                                        </div>
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </section>
                @empty
                    <div class="notif-empty">
                        <p>
                            @if ($mataKuliah->isEmpty())
                                Kamu belum mengampu mata kuliah, jadi belum ada pengumpulan untuk ditampilkan.
                            @elseif ($filterAktif)
                                Tidak ada pengumpulan yang cocok dengan filter ini.
                            @else
                                Belum ada pengumpulan pada periode ini.
                            @endif
                        </p>
                        @if ($filterAktif)
                            <a class="notif-btn" href="{{ route('dosen.notifikasi', array_filter(['tab' => $tab === 'semua' ? null : $tab])) }}">Reset filter</a>
                        @endif
                    </div>
                @endforelse

                @if ($total > 0)
                    <nav class="notif-pager" aria-label="Halaman pengumpulan">
                        <p class="notif-pager__info">
                            Menampilkan {{ $halaman->firstItem() }}&ndash;{{ $halaman->lastItem() }} dari {{ $total }} pengumpulan
                        </p>

                        @if ($halaman->lastPage() > 1)
                            <div class="notif-pager__links">
                                @if ($halaman->onFirstPage())
                                    <span class="notif-page notif-page--wide is-disabled">Sebelumnya</span>
                                @else
                                    <a class="notif-page notif-page--wide" href="{{ $halaman->previousPageUrl() }}" rel="prev">Sebelumnya</a>
                                @endif

                                @for ($p = max(1, $halaman->currentPage() - 2); $p <= min($halaman->lastPage(), $halaman->currentPage() + 2); $p++)
                                    @if ($p === $halaman->currentPage())
                                        <span class="notif-page is-active" aria-current="page">{{ $p }}</span>
                                    @else
                                        <a class="notif-page" href="{{ $halaman->url($p) }}">{{ $p }}</a>
                                    @endif
                                @endfor

                                @if ($halaman->hasMorePages())
                                    <a class="notif-page notif-page--wide" href="{{ $halaman->nextPageUrl() }}" rel="next">Berikutnya</a>
                                @else
                                    <span class="notif-page notif-page--wide is-disabled">Berikutnya</span>
                                @endif
                            </div>
                        @endif
                    </nav>
                @endif
            </div>

            {{-- KOLOM SAMPING --}}
            <aside class="notif-side">

                <section class="notif-card">
                    <p class="notif-card__eyebrow">ANTREAN</p>
                    <h2 class="notif-card__title">Antrean Penilaian</h2>
                    <p class="notif-card__desc">
                        Mata kuliah dengan pengumpulan yang paling banyak belum kamu nilai.
                    </p>

                    @forelse ($antrean as $mkAntre)
                        <div class="notif-course notif-course--{{ $mkAntre['level'] }}">
                            <div class="notif-course__top">
                                <a href="{{ route('dosen.mata-kuliah.show', $mkAntre['id']) }}">{{ $mkAntre['nama'] }}</a>
                                <strong>{{ $mkAntre['belum'] }} belum</strong>
                            </div>
                            <div class="notif-bar" role="img" aria-label="{{ $mkAntre['persen'] }} persen sudah dinilai">
                                <div class="notif-bar__fill" style="width: {{ $mkAntre['persen'] }}%;"></div>
                            </div>
                            <p class="notif-course__sub">
                                {{ $mkAntre['kode'] }} &middot; {{ $mkAntre['dinilai'] }} dari {{ $mkAntre['total'] }} pengumpulan sudah dinilai
                            </p>
                        </div>
                    @empty
                        <p class="notif-card__empty">Belum ada pengumpulan di mata kuliahmu.</p>
                    @endforelse
                </section>

                <section class="notif-card">
                    <p class="notif-card__eyebrow">TENGGAT</p>
                    <h2 class="notif-card__title">Jadwal Batas Tugas</h2>
                    <p class="notif-card__desc">
                        Tugas terbit di mata kuliahmu yang batas pengumpulannya paling dekat.
                    </p>

                    @forelse ($tenggat as $t)
                        <a class="notif-deadline {{ $t['mendesak'] ? 'is-urgent' : '' }}" href="{{ route('dosen.tugas.show', $t['id']) }}">
                            <span class="notif-deadline__top">
                                <span class="notif-deadline__title">{{ $t['judul'] }}</span>
                                <span class="notif-deadline__time">{{ $t['jam'] }}</span>
                            </span>
                            <span class="notif-deadline__sub">{{ $t['kode'] }} &middot; {{ $t['terkumpul'] }}/{{ $t['mahasiswa'] }} terkumpul</span>
                            <span class="notif-deadline__left">{{ $t['tanggal'] }} &middot; {{ $t['sisa'] }}</span>
                        </a>
                    @empty
                        <p class="notif-card__empty">Tidak ada tugas dengan batas waktu mendatang.</p>
                    @endforelse
                </section>
            </aside>
        </div>
    </section>
</x-layout>