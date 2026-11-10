<x-layout title="Dashboard Admin" role="admin" full-footer>
<style>
/* Statistik utama — khusus Dashboard Admin */
.admin-dashboard .admin-metrics {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 1rem;
    width: 100%;
}

.admin-dashboard .admin-metric {
    position: relative;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    min-width: 0;
    min-height: 218px;
    padding: 1.35rem;
    background: var(--color-surface);
    border: 1px solid var(--color-line);
    border-radius: 24px;
    box-shadow: 0 7px 22px rgba(0, 0, 0, .06);
    transition: transform .2s, box-shadow .2s;
}

.admin-dashboard .admin-metric::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: currentColor;
    opacity: .85;
}

.admin-dashboard .admin-metric::after {
    content: "";
    position: absolute;
    right: -32px;
    bottom: -42px;
    width: 125px;
    height: 125px;
    border-radius: 50%;
    background: currentColor;
    opacity: .055;
    pointer-events: none;
    transition: transform .4s ease, opacity .3s ease;
}

.admin-dashboard .admin-metric:hover {
    transform: translateY(-4px);
    box-shadow: 0 15px 32px rgba(0, 0, 0, .14);
}

.admin-dashboard .admin-metric:hover::after {
    transform: translate(-12px, -10px) scale(1.2);
    opacity: .12;
}

.admin-dashboard .admin-metric--pink { color: var(--metric-pink); --accent: var(--metric-pink); }

.admin-dashboard .admin-metric--purple { color: var(--metric-purple); --accent: var(--metric-purple); }

.admin-dashboard .admin-metric--blue { color: var(--metric-blue); --accent: var(--metric-blue); }

.admin-dashboard .admin-metric--orange { color: var(--metric-orange); --accent: var(--metric-orange); }

.admin-dashboard .admin-metric__top {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.25rem;
}

.admin-dashboard .admin-metric__icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 46px;
    height: 46px;
    border-radius: 14px;
    background: var(--accent, currentColor);
    color: #fff;
}

.admin-dashboard .admin-metric__icon svg {
    width: 22px;
    height: 22px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.8;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.admin-dashboard .admin-metric__index {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 38px;
    height: 27px;
    padding: 0 .65rem;
    border: 1px solid currentColor;
    border-radius: 999px;
    background: var(--color-surface);
    font-size: .7rem;
    font-weight: 850;
    letter-spacing: .13em;
    color: currentColor;
}

.admin-dashboard .admin-metric__number {
    position: relative;
    z-index: 1;
    margin: 0;
    font-size: 3rem;
    line-height: .95;
    font-weight: 850;
    letter-spacing: -.055em;
    color: var(--color-text);
}

.admin-dashboard .admin-metric__label {
    position: relative;
    z-index: 1;
    margin-top: .48rem;
    font-size: .98rem;
    line-height: 1.25;
    font-weight: 850;
    color: var(--color-text);
}

.admin-dashboard .admin-metric__caption {
    position: relative;
    z-index: 1;
    max-width: 92%;
    margin-top: .38rem;
    font-size: .74rem;
    line-height: 1.45;
    color: var(--color-muted);
}

/* Panel utama */
.admin-dashboard .admin-course-panel {
    position: relative;
    margin-top: 22px;
    padding: 30px 32px 28px;
    background: var(--color-surface);
    border: 1px solid var(--color-line);
    border-radius: 24px;
    overflow: hidden;
    transition: transform .25s, box-shadow .25s, border-color .25s;
}

.admin-dashboard .admin-course-panel:hover {
    transform: translateY(-3px);
    box-shadow: 0 18px 40px rgba(0, 0, 0, .14);
    border-color: var(--color-line-hover);
}

.admin-dashboard .admin-course-panel::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
        width: 6px;
    height: 100%;
}

.admin-dashboard .admin-course-panel::before {
    background: linear-gradient(180deg, var(--color-primary), var(--color-card-4-from));
}

.admin-dashboard .admin-course-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 30px;
    padding-bottom: 22px;
    border-bottom: 1px solid var(--color-line);
}

.admin-dashboard .admin-course-eyebrow {
    margin: 0 0 7px;
    color: var(--color-crimson);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .16em;
}

.admin-dashboard .admin-course-title {
    margin: 0;
    color: var(--color-text);
    font-size: 25px;
    line-height: 1.15;
}

.admin-dashboard .admin-course-description {
    max-width: 600px;
    margin: 8px 0 0;
    color: var(--color-muted);
    font-size: 13px;
    line-height: 1.6;
}

/* Kotak jumlah dibuat konsisten */
.admin-dashboard .admin-course-total {
    box-sizing: border-box;
    display: flex;
    flex: 0 0 180px;
    flex-direction: column;
    justify-content: center;
    width: 180px;
    min-width: 180px;
    min-height: 90px;
    padding: 14px 17px;
    text-align: right;
    background: var(--grad-soft);
    border: 1px solid var(--color-line);
    border-radius: 15px;
    box-shadow: 0 3px 10px rgba(0, 0, 0, .06);
    transition: transform .22s, box-shadow .22s, background .22s;
}

.admin-dashboard .admin-course-total:hover {
    transform: translateY(-3px) scale(1.02);
    box-shadow: 0 10px 24px rgba(0, 0, 0, .16);
    background: var(--grad-soft-hover);
}

.admin-dashboard .admin-course-total span {
    display: block;
    margin-bottom: 7px;
    color: var(--color-muted);
    font-size: 11px;
    line-height: 1.35;
    font-weight: 700;
}

.admin-dashboard .admin-course-total strong {
    display: block;
    color: var(--color-crimson);
    font-size: 32px;
    line-height: 1;
    font-weight: 850;
    letter-spacing: -.035em;
    font-variant-numeric: tabular-nums;
}

.admin-dashboard .admin-course-empty {
    padding: 32px 10px;
    color: var(--color-muted);
    text-align: center;
    font-size: 13px;
}

/* Ringkasan mata kuliah — palet warna seragam */
.admin-dashboard .admin-course-panel {
    background: var(--color-surface);
    border-color: var(--color-line);
}

.admin-dashboard .admin-course-columns,
.admin-dashboard .admin-course-item {
    display: grid;
    grid-template-columns: minmax(0, 1.5fr) minmax(180px, 1fr) 90px 150px;
    gap: 20px;
    align-items: center;
}

.admin-dashboard .admin-course-columns {
    margin-top: 20px;
    padding: 0 4px 11px;
    border-bottom: 1px solid var(--color-line);
}

.admin-dashboard .admin-course-columns span {
    color: var(--color-muted);
    font-size: 10px;
    font-weight: 800;
    line-height: 1.2;
    text-transform: uppercase;
    letter-spacing: .09em;
}

.admin-dashboard .admin-course-list {
    display: flex;
    flex-direction: column;
}

.admin-dashboard .admin-course-item {
    min-height: 82px;
    padding: 15px 4px;
    border-bottom: 1px solid var(--color-line);
    transition: background .2s, transform .2s, padding-left .2s;
}

.admin-dashboard .admin-course-item:last-child {
    border-bottom: 0;
}

.admin-dashboard .admin-course-item:hover {
    padding-left: 9px;
    background: linear-gradient(
        90deg,
        color-mix(in srgb, var(--color-primary) 14%, transparent),
        transparent
    );
    transform: translateX(4px);
}

.admin-dashboard .admin-course-name {
    min-width: 0;
}

.admin-dashboard .admin-course-code {
    display: inline-block;
    margin-bottom: 6px;
    padding: 5px 9px;
    color: var(--color-crimson);
    background: var(--color-tint);
    border: 1px solid var(--color-line);
    border-radius: 7px;
    font-size: 12px;
    font-weight: 800;
    line-height: 1;
    letter-spacing: .07em;
}

.admin-dashboard .admin-course-name strong {
    display: block;
    color: var(--color-text);
    font-size: 14px;
    line-height: 1.4;
    font-weight: 700;
}

.admin-dashboard .admin-course-lecturer {
    min-width: 0;
    color: var(--color-ink-2);
    font-size: 13px;
    line-height: 1.4;
    font-weight: 600;
}

.admin-dashboard .admin-course-sks {
    color: var(--color-ink-2);
    font-size: 14px;
    line-height: 1.4;
    font-weight: 700;
}

.admin-dashboard .admin-course-students {
    color: var(--color-ink-2);
    font-size: 13px;
    line-height: 1.4;
    font-weight: 700;
}

/* Catatan */
.admin-dashboard .admin-dashboard-note {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 22px;
    padding: 15px 18px;
    color: var(--color-muted);
    background: var(--color-surface);
    border: 1px solid var(--color-line);
    border-radius: 16px;
}

.admin-dashboard .admin-dashboard-note__mark {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    flex: 0 0 22px;
    color: var(--color-crimson);
    background: var(--color-tint);
    border: 1px solid var(--color-line);
    border-radius: 50%;
    font-size: 11px;
    font-weight: 800;
}

.admin-dashboard .admin-dashboard-note p {
    margin: 0;
    font-size: 12px;
    line-height: 1.5;
}

/* Responsif tablet */
@media (max-width: 1100px) {
    .admin-dashboard .admin-metrics {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .admin-dashboard .admin-course-columns,
    .admin-dashboard .admin-course-item {
        grid-template-columns: minmax(0, 1.5fr) minmax(150px, 1fr) 75px 125px;
        gap: 12px;
    }
}

/* Responsif layar kecil */
@media (max-width: 900px) {
    .admin-dashboard .admin-course-columns {
        display: none;
    }

    .admin-dashboard .admin-course-item {
        grid-template-columns: 1fr;
        gap: 8px;
        padding: 17px 4px;
    }
}

@media (max-width: 760px) {
    .admin-dashboard .admin-course-panel {
        padding: 25px 22px 23px;
    }

    .admin-dashboard .admin-course-header {
        flex-direction: column;
        gap: 18px;
    }

    .admin-dashboard .admin-course-total {
        min-width: 0;
        min-height: 70px;
        width: 100%;
        text-align: left;
        align-items: flex-start;
    }
}

@media (max-width: 520px) {
    .admin-dashboard .admin-metrics {
        grid-template-columns: 1fr;
    }
}

.admin-dashboard .admin-chart {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    align-items: end;
    gap: 12px;
    min-height: 190px;
    padding: 18px 4px 0;
    border-bottom: 1px solid var(--color-line);
}

@media (max-width: 520px) {
    .admin-dashboard .admin-chart { gap: 7px; }
}
</style>

<section class="admin-dashboard">
    {{-- HEADER (banner gaya dosen/mahasiswa) --}}
    <x-dash-hero
        portal="KampusLMS / Admin"
        title="Dashboard"
        sub="Pantau data dan aktivitas pembelajaran KampusLMS."
        status="Sistem aktif"
        :bell="false"
        search-label="Cari data..."
        target=".admin-metric, .admin-course-item"
    />

    {{-- STATISTIK UTAMA --}}
    <section class="admin-metrics" aria-label="Ringkasan KampusLMS">
        <article class="admin-metric admin-metric--pink">
            <div class="admin-metric__top">
                <div class="admin-metric__icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M3 21v-2a6 6 0 0 1 12 0v2"></path>
                        <path d="M16 3.5a4 4 0 0 1 0 7"></path>
                        <path d="M18 14a5 5 0 0 1 3 4.6V21"></path>
                    </svg>
                </div>
                <span class="admin-metric__index">01</span>
            </div>

            <div class="admin-metric__number">{{ $totalDosen }}</div>
            <div class="admin-metric__label">Dosen</div>
            <div class="admin-metric__caption">
                Pengajar yang mengelola kegiatan pembelajaran.
            </div>
        </article>

        <article class="admin-metric admin-metric--purple">
            <div class="admin-metric__top">
                <div class="admin-metric__icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M2 10l10-5 10 5-10 5L2 10z"></path>
                        <path d="M6 12.5V17c2.5 2 9.5 2 12 0v-4.5"></path>
                        <path d="M22 10v6"></path>
                    </svg>
                </div>
                <span class="admin-metric__index">02</span>
            </div>

            <div class="admin-metric__number">{{ $totalMahasiswa }}</div>
            <div class="admin-metric__label">Mahasiswa</div>
            <div class="admin-metric__caption">
                Peserta yang mengikuti kegiatan belajar di KampusLMS.
            </div>
        </article>

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
                <span class="admin-metric__index">03</span>
            </div>

            <div class="admin-metric__number">{{ $totalMataKuliah }}</div>
            <div class="admin-metric__label">Mata Kuliah</div>
            <div class="admin-metric__caption">
                Ruang belajar untuk kegiatan perkuliahan.
            </div>
        </article>

        <article class="admin-metric admin-metric--orange">
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

            <div class="admin-metric__number">{{ $totalTugas }}</div>
            <div class="admin-metric__label">Tugas</div>
            <div class="admin-metric__caption">
                Aktivitas yang dikerjakan dan dikumpulkan mahasiswa.
            </div>
        </article>
    </section>

        {{-- RINGKASAN MATA KULIAH --}}
    <section class="admin-course-panel">
        <div class="admin-course-header">
            <div>
                <p class="admin-course-eyebrow">KATALOG AKADEMIK</p>
                <h2 class="admin-course-title">Ringkasan Mata Kuliah</h2>
                <p class="admin-course-description">
                    Daftar mata kuliah yang tersedia di KampusLMS beserta dosen pengampu,
                    jumlah SKS, dan jumlah mahasiswa yang terdaftar.
                </p>
            </div>

            <div class="admin-course-total">
                <span>Total mata kuliah</span>
                <strong>{{ $totalMataKuliah }}</strong>
            </div>
        </div>

        <div class="admin-course-columns">
            <span>Mata Kuliah</span>
            <span>Dosen Pengampu</span>
            <span>Beban SKS</span>
            <span>Mahasiswa Terdaftar</span>
        </div>

        <div class="admin-course-list">
            @forelse ($ringkasanMataKuliah as $course)
                <div class="admin-course-item">
                    <div class="admin-course-name">
                        <span class="admin-course-code">{{ $course->code }}</span>
                        <strong>{{ $course->name }}</strong>
                    </div>

                    <div class="admin-course-lecturer">
                        {{ $course->lecturer?->name ?? 'Belum ditentukan' }}
                    </div>

                    <div class="admin-course-sks">{{ $course->sks }} SKS</div>
                    <div class="admin-course-students">{{ $course->students_count }} mahasiswa</div>
                </div>
            @empty
                <div class="admin-course-empty">
                    Belum ada mata kuliah yang tersedia.
                </div>
            @endforelse
        </div>

        {{ $ringkasanMataKuliah->links('pagination::bootstrap-4') }}
    </section>

    {{-- CATATAN --}}
    <div class="admin-dashboard-note">
        <span class="admin-dashboard-note__mark">i</span>
        <p>Data pada dashboard diperbarui langsung dari data KampusLMS.</p>
    </div>
</section>
</x-layout>