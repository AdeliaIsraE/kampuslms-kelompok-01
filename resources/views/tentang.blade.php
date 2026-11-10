<x-layout title="Tentang Kelompok" full-footer>

    {{-- Hero --}}
    <div class="hero-tentang">
        <h1>KampusLMS</h1>
        <p>
            LMS Kampus adalah sistem pembelajaran daring yang membantu dosen dan mahasiswa mengelola mata kuliah, tugas, dan penilaian dalam satu tempat.
        </p>
    </div>

    {{-- Tim / Kelompok --}}
    @php
        $anggota = [
            ['nama' => 'Ade Putri Amanda', 'role' => 'Front-End Developer', 'nim' => '10241002'],
            ['nama' => 'Adelia Cyntia Renata', 'role' => 'Front-End Developer', 'nim' => '10241003'],
            ['nama' => 'Adelia Isra Ekaputri', 'role' => 'Full-Stack Developer', 'nim' => '10241004'],
            ['nama' => 'Alya Auralia', 'role' => 'Back-End Developer', 'nim' => '10241008'],
            ['nama' => 'Andika Putra Pratama', 'role' => 'Back-End Developer', 'nim' => '10241010'],
        ];
    @endphp
    <h2 class="tentang-heading">Kelompok 01</h2>
    <div class="tim-grid" style="--cols: {{ count($anggota ?? []) ?: 5 }};">

        @foreach ($anggota as $orang)
            @php
                $inisial = collect(explode(' ', $orang['nama']))->map(fn($k) => strtoupper($k[0]))->take(2)->join('');
            @endphp
            <article class="tim-card">
                <div class="tim-avatar">{{ $inisial }}</div>
                <h3 class="tim-nama">{{ $orang['nama'] }}</h3>
                <p class="tim-role">{{ $orang['role'] }}</p>
                <p class="tim-nim">{{ $orang['nim'] }}</p>

                <span class="tim-number" aria-hidden="true">{{ $loop->iteration }}</span>
            </article>
        @endforeach
    </div>

</x-layout>