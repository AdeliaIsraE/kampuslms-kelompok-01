{{--
    Pusat Notifikasi admin.
    Data dari AdminNotifikasiController (feed gabungan materi, tugas, pengumpulan).
    Gaya: resources/css/dashboard/notifikasi.css; kartu metrik memakai .admin-metric
    (components/dashboard.css) dan banner memakai .dash-hero (dashboard/dosen-mahasiswa.css).
--}}
@php
    // Parameter URL yang dipertahankan saat pindah tab (nilai bawaan tidak ditulis ke URL).
    $urlTab = fn (string $kunci) => route('admin.notifikasi', array_filter([
        'tab' => $kunci === 'semua' ? null : $kunci,
        'q' => $q !== '' ? $q : null,
        'periode' => $periode === '30-hari' ? null : $periode,
    ]));

    $filterAktif = $q !== '' || $periode !== '30-hari';
@endphp

<x-layout title="Notifikasi Admin" role="admin" full-footer>
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
                    <p class="dash-hero__portal">KampusLMS / Admin</p>
                    <h1 class="dash-hero__title">Pusat Notifikasi</h1>
                </div>
            </div>
            <p class="dash-hero__sub">
                Pantau materi yang diunggah dosen, tugas yang diterbitkan, dan pengumpulan tugas mahasiswa.
            </p>
            <span class="dash-hero__status">
                <i></i>{{ $ringkasan['aktivitasHariIni'] }} aktivitas hari ini, {{ $belumDibaca }} belum dibaca
            </span>
        </header>

        {{-- KARTU RINGKASAN (7 hari terakhir) --}}
        <section class="admin-metrics" aria-label="Ringkasan aktivitas 7 hari terakhir">
            <article class="admin-metric admin-metric--blue">
                <div class="admin-metric__top">
                    <div class="admin-metric__icon">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21.5v-16z"></path>
                            <path d="M4 5.5v16"></path>
                            <path d="M8 7h8"></path>
                            <path d="M8 11h6"></path>
                        </svg>
                    </div>
                    <span class="admin-metric__index">01</span>
                </div>
                <div class="admin-metric__number">{{ $ringkasan['materi'] }}</div>
                <div class="admin-metric__label">Materi Baru Diunggah</div>
                <div class="admin-metric__caption">
                    7 hari terakhir: {{ $ringkasan['materiBerkas'] }} berkas dan {{ $ringkasan['materiTautan'] }} tautan.
                    {{ $ringkasan['materiHariIni'] }} diunggah hari ini.
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
                    <span class="admin-metric__index">02</span>
                </div>
                <div class="admin-metric__number">{{ $ringkasan['tugas'] }}</div>
                <div class="admin-metric__label">Tugas Dipublikasi</div>
                <div class="admin-metric__caption">
                    7 hari terakhir, {{ $ringkasan['tugasHariIni'] }} terbit hari ini.
                    Masih ada {{ $ringkasan['tugasDraf'] }} tugas berstatus draf.
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
                    <span class="admin-metric__index">03</span>
                </div>
                <div class="admin-metric__number">{{ $ringkasan['kumpul'] }}</div>
                <div class="admin-metric__label">Pengumpulan Masuk</div>
                <div class="admin-metric__caption">
                    @if ($ringkasan['tepatWaktu'] !== null)
                        {{ $ringkasan['tepatWaktu'] }}% tepat waktu, {{ $ringkasan['kumpulTelat'] }} terlambat (7 hari terakhir).
                    @else
                        Belum ada pengumpulan dalam 7 hari terakhir.
                    @endif
                </div>
            </article>

            <article class="admin-metric admin-metric--orange">
                <div class="admin-metric__top">
                    <div class="admin-metric__icon">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <circle cx="12" cy="12" r="9"></circle>
                            <path d="M12 7v5l3 2"></path>
                        </svg>
                    </div>
                    <span class="admin-metric__index">04</span>
                </div>
                <div class="admin-metric__number">{{ $ringkasan['menungguNilai'] }}</div>
                <div class="admin-metric__label">Menunggu Penilaian</div>
                <div class="admin-metric__caption">
                    Pengumpulan yang belum diberi nilai oleh dosen pengampu.
                </div>
            </article>
        </section>

        <div class="notif-layout">

            {{-- KOLOM UTAMA: filter + feed --}}
            <div class="notif-main">

                <div class="notif-toolbar">
                    <nav class="notif-tabs" aria-label="Jenis aktivitas">
                        @foreach ($tabs as $kunci => $info)
                            <a href="{{ $urlTab($kunci) }}"
                               class="notif-tab {{ $tab === $kunci ? 'is-active' : '' }}"
                               @if ($tab === $kunci) aria-current="page" @endif>
                                {{ $info['label'] }}
                                <span class="notif-tab__count">{{ $info['jumlah'] }}</span>
                            </a>
                        @endforeach
                    </nav>

                    <form method="GET" action="{{ route('admin.notifikasi') }}" class="notif-filter">
                        <input type="hidden" name="tab" value="{{ $tab }}">

                        <label class="notif-search">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="11" cy="11" r="7"></circle>
                                <path d="M21 21l-4.3-4.3"></path>
                            </svg>
                            <input type="search" name="q" value="{{ $q }}" maxlength="100"
                                   placeholder="Cari nama, judul, atau mata kuliah..." aria-label="Cari aktivitas">
                        </label>

                        <select name="periode" class="notif-select" aria-label="Periode">
                            @foreach ($opsiPeriode as $kunci => $label)
                                <option value="{{ $kunci }}" @selected($periode === $kunci)>{{ $label }}</option>
                            @endforeach
                        </select>

                        <button type="submit" class="notif-btn notif-btn--primary">Terapkan</button>

                        @if ($filterAktif)
                            <a href="{{ route('admin.notifikasi', array_filter(['tab' => $tab === 'semua' ? null : $tab])) }}"
                               class="notif-btn">Reset</a>
                        @endif
                    </form>
                </div>

                @if ($belumDibaca > 0)
                    <form method="POST" action="{{ route('admin.notifikasi.dibaca-semua') }}" class="notif-markall">
                        @csrf
                        <button type="submit" class="notif-btn">Tandai semua dibaca ({{ $belumDibaca }})</button>
                    </form>
                @endif

                @forelse ($grupHari as $grup)
                    <section class="notif-day">
                        <div class="notif-day__head">
                            <h2>{{ $grup['label'] }}</h2>
                            <span>{{ $grup['jumlah'] }} aktivitas</span>
                        </div>

                        <div class="notif-list">
                            @foreach ($grup['items'] as $item)
                                <article class="notif-item notif-item--{{ $item['nada'] }}">
                                    <div class="notif-item__icon" aria-hidden="true">
                                        @if ($item['jenis'] === 'materi')
                                            <svg viewBox="0 0 24 24">
                                                <path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21.5v-16z"></path>
                                                <path d="M4 5.5v16"></path>
                                                <path d="M8 7h8"></path>
                                                <path d="M8 11h6"></path>
                                            </svg>
                                        @elseif ($item['jenis'] === 'tugas')
                                            <svg viewBox="0 0 24 24">
                                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                                <path d="M14 2v6h6"></path>
                                                <path d="M8 13h8"></path>
                                                <path d="M8 17h5"></path>
                                            </svg>
                                        @elseif ($item['nada'] === 'terlambat')
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
                                                <span class="notif-chip notif-chip--muted">{{ $item['peran'] }}</span>
                                                @foreach ($item['chips'] as [$teks, $varian])
                                                    <span class="notif-chip notif-chip--{{ $varian }}">{{ $teks }}</span>
                                                @endforeach
                                            </div>
                                            <time class="notif-item__time" datetime="{{ $item['at']->toIso8601String() }}" title="{{ $item['lengkap'] }}">
                                                {{ $item['relatif'] }}
                                            </time>
                                        </div>

                                        <p class="notif-item__text">
                                            {{ $item['aksi'] }}
                                            <strong>"{{ $item['judul'] }}"</strong>
                                            pada mata kuliah
                                            <a href="{{ route('admin.mata-kuliah.show', $item['course_id']) }}">{{ $item['course_name'] }}</a>.
                                        </p>

                                        <div class="notif-item__meta">
                                            <span class="notif-meta notif-meta--kode">{{ $item['course_code'] }}</span>
                                            @foreach ($item['meta'] as $meta)
                                                <span class="notif-meta">{{ $meta }}</span>
                                            @endforeach
                                            <span class="notif-meta">Pukul {{ $item['jam'] }}</span>
                                        </div>

                                        @if ($item['lampiran'])
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
                                        @endif

                                        @if ($item['catatan'])
                                            <p class="notif-note">{{ $item['catatan'] }}</p>
                                        @endif

                                        <div class="notif-item__actions">
                                            @if ($item['jenis'] === 'materi')
                                                <a class="notif-btn notif-btn--primary" href="{{ route('admin.materi.show', $item['id']) }}">Buka Materi</a>
                                            @elseif ($item['jenis'] === 'tugas')
                                                <a class="notif-btn notif-btn--primary" href="{{ route('admin.tugas.show', $item['id']) }}">Lihat Tugas</a>
                                            @else
                                                <a class="notif-btn notif-btn--primary" href="{{ route('pengumpulan.unduh', $item['id']) }}">Unduh Berkas</a>
                                                <a class="notif-btn" href="{{ route('admin.tugas.show', $item['assignment_id']) }}">Lihat Tugas</a>
                                            @endif
                                            <a class="notif-btn" href="{{ route('admin.mata-kuliah.show', $item['course_id']) }}">Lihat Mata Kuliah</a>
                                            <form method="POST" action="{{ route('admin.notifikasi.dibaca', [$item['jenis'], $item['id']]) }}" class="notif-inline-form">
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
                            @if ($filterAktif)
                                Tidak ada aktivitas yang cocok dengan filter ini.
                            @else
                                Belum ada aktivitas pada periode ini.
                            @endif
                        </p>
                        @if ($filterAktif)
                            <a class="notif-btn" href="{{ route('admin.notifikasi', array_filter(['tab' => $tab === 'semua' ? null : $tab])) }}">Reset filter</a>
                        @endif
                    </div>
                @endforelse

                @if ($total > 0)
                    <nav class="notif-pager" aria-label="Halaman aktivitas">
                        <p class="notif-pager__info">
                            Menampilkan {{ $halaman->firstItem() }}&ndash;{{ $halaman->lastItem() }} dari {{ $total }} aktivitas
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
                    <p class="notif-card__eyebrow">MATA KULIAH</p>
                    <h2 class="notif-card__title">Pengumpulan Tertinggi</h2>
                    <p class="notif-card__desc">
                        Persentase pengumpulan dari seluruh tugas terbit dan mahasiswa terdaftar.
                    </p>

                    @forelse ($mkAktif as $mk)
                        <div class="notif-course notif-course--{{ $mk['level'] }}">
                            <div class="notif-course__top">
                                <a href="{{ route('admin.mata-kuliah.show', $mk['id']) }}">{{ $mk['nama'] }}</a>
                                <strong>{{ $mk['persen'] }}%</strong>
                            </div>
                            <div class="notif-bar" role="img" aria-label="{{ $mk['persen'] }} persen">
                                <div class="notif-bar__fill" style="width: {{ $mk['persen'] }}%;"></div>
                            </div>
                            <p class="notif-course__sub">
                                {{ $mk['kode'] }} &middot; {{ $mk['masuk'] }} pengumpulan &middot;
                                {{ $mk['tugas'] }} tugas &times; {{ $mk['mahasiswa'] }} mahasiswa
                            </p>
                        </div>
                    @empty
                        <p class="notif-card__empty">Belum ada mata kuliah dengan tugas terbit.</p>
                    @endforelse
                </section>

                <section class="notif-card">
                    <p class="notif-card__eyebrow">TENGGAT</p>
                    <h2 class="notif-card__title">Batas Waktu Terdekat</h2>
                    <p class="notif-card__desc">
                        Tugas terbit yang batas pengumpulannya paling dekat.
                    </p>

                    @forelse ($tenggat as $t)
                        <a class="notif-deadline {{ $t['mendesak'] ? 'is-urgent' : '' }}" href="{{ route('admin.tugas.show', $t['id']) }}">
                            <span class="notif-deadline__top">
                                <span class="notif-deadline__title">{{ $t['judul'] }}</span>
                                <span class="notif-deadline__time">{{ $t['jam'] }}</span>
                            </span>
                            <span class="notif-deadline__sub">{{ $t['kode'] }} &middot; {{ $t['dosen'] }}</span>
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