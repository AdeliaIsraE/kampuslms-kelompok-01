
{{--
    View show: detail satu mata kuliah (banner, kode, SKS, dosen, status, deskripsi).

    Style (.mk-*) ada di resources/css/components/courses.css.
    Variabel dari CourseController@show:
      $mataKuliah  -> objek model Course
      $routePrefix -> mis. 'admin.mata-kuliah' (view yang sama dipakai tiap peran)
--}}
<x-layout :title="$mataKuliah->name">

    @php
        $user       = auth()->user();
        $mkPatterns = ['diamond', 'triangle', 'circle', 'diamond', 'triangle'];
        $mkIndex    = (crc32($mataKuliah->code) % 5) + 1;
        $mkPattern  = $mkPatterns[$mkIndex - 1];

        $canEdit = Route::has($routePrefix . '.edit')
            && $user->can('update', $mataKuliah);

        // $enrolled dikirim controller hanya bila boleh melihat daftar peserta;
        // $available hanya ada bila route tambah mahasiswa tersedia.
        $canEnroll = isset($available)
            && Route::has($routePrefix . '.mahasiswa.store');
    @endphp

    <a href="{{ route($routePrefix . '.index') }}" class="mk-back-link">
        &larr; Kembali ke Daftar Mata Kuliah
    </a>

    @if ($errors->any())
        <div class="alert alert-error" role="alert">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <article class="mk-card">

        <div class="mk-banner mk-pattern-{{ $mkPattern }} mk-bg-{{ $mkIndex }}">
            <span class="mk-badge">{{ $mataKuliah->code }}</span>
        </div>

        <div class="mk-body">

            <h1 class="mk-title">{{ $mataKuliah->name }}</h1>

            <div class="mk-meta-row">
                <div class="mk-meta-item">
                    <span class="mk-meta-label">Kode</span>
                    <span class="mk-meta-value">{{ $mataKuliah->code }}</span>
                </div>

                <div class="mk-meta-item">
                    <span class="mk-meta-label">SKS</span>
                    <span class="mk-meta-value">{{ $mataKuliah->sks }} SKS</span>
                </div>

                <div class="mk-meta-item">
                    <span class="mk-meta-label">Dosen Pengampu</span>
                    <span class="mk-meta-value">{{ $mataKuliah->lecturer->name ?? '-' }}</span>
                </div>

                <div class="mk-meta-item">
                    <span class="mk-meta-label">Status</span>
                    <span class="mk-meta-value">{{ ucfirst($mataKuliah->status) }}</span>
                </div>
            </div>

            <h2 class="mk-desc-label">Deskripsi</h2>

            @if (filled($mataKuliah->description))
                {{-- e() meng-escape HTML dulu, baru baris baru diubah jadi <br> --}}
                <p class="mk-desc-text">{!! nl2br(e($mataKuliah->description)) !!}</p>
            @else
                <p class="mk-desc-text">Belum ada deskripsi untuk mata kuliah ini.</p>
            @endif

            {{-- Pintasan ke materi & tugas --}}
            @if (Route::has($routePrefix . '.materi.index') || Route::has($routePrefix . '.tugas.index'))
                <div class="mk-shortcuts">
                    @if (Route::has($routePrefix . '.materi.index'))
                        <a href="{{ route($routePrefix . '.materi.index', $mataKuliah) }}" class="mk-shortcut">
                            <span class="mk-shortcut-title">Materi</span>
                            <span class="mk-shortcut-count">{{ $mataKuliah->materials_count }} materi</span>
                        </a>
                    @endif

                    @if (Route::has($routePrefix . '.tugas.index'))
                        <a href="{{ route($routePrefix . '.tugas.index', $mataKuliah) }}" class="mk-shortcut">
                            <span class="mk-shortcut-title">Tugas</span>
                            <span class="mk-shortcut-count">
                                {{ $user->role === 'mahasiswa'
                                    ? $mataKuliah->published_assignments_count
                                    : $mataKuliah->assignments_count }} tugas
                            </span>
                        </a>
                    @endif
                </div>
            @endif

            {{-- ===== Mahasiswa terdaftar (admin / dosen pengampu) ===== --}}
            @isset($enrolled)
                <section class="mk-enroll">
                    <h2 class="mk-desc-label">Mahasiswa Terdaftar ({{ $enrolled->count() }})</h2>

                    @if ($enrolled->isEmpty())
                        <p class="mk-desc-text">Belum ada mahasiswa yang terdaftar pada mata kuliah ini.</p>
                    @else
                        <div class="mk-enroll-wrap">
                            <table class="mk-enroll-table">
                                <thead>
                                    <tr>
                                        <th>Nama</th>
                                        <th>NIM</th>
                                        <th>Terdaftar</th>
                                        @if ($canEnroll)
                                            <th class="mk-enroll-actions">Aksi</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($enrolled as $student)
                                        <tr>
                                            <td>{{ $student->name }}</td>
                                            <td>{{ $student->nim_nip }}</td>
                                            <td>
                                                {{ $student->pivot->enrolled_at
                                                    ? \Illuminate\Support\Carbon::parse($student->pivot->enrolled_at)->format('d M Y')
                                                    : '-' }}
                                            </td>
                                            @if ($canEnroll)
                                                <td class="mk-enroll-actions">
                                                    <form
                                                        action="{{ route($routePrefix . '.mahasiswa.destroy', [$mataKuliah, $student->id]) }}"
                                                        method="POST"
                                                        onsubmit="return confirm('Keluarkan mahasiswa ini dari mata kuliah?');"
                                                    >
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="mk-enroll-remove">Keluarkan</button>
                                                    </form>
                                                </td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    {{-- Form tambah mahasiswa --}}
                    @if ($canEnroll)
                        <h3 class="mk-enroll-subtitle">Tambah Mahasiswa</h3>

                        {{-- Form pencarian mahasiswa --}}
                        <form
                            action="{{ route($routePrefix . '.show', $mataKuliah) }}"
                            method="GET"
                            id="mk-student-search-form"
                        >
                            <input
                                type="search"
                                name="q"
                                id="mk-student-filter"
                                class="form-control"
                                placeholder="Cari nama atau NIM..."
                                value="{{ request('q') }}"
                                autocomplete="off"
                            >

                            <button type="submit" class="btn btn-primary">
                                Cari
                            </button>
                        </form>

                        {{-- Pencarian otomatis saat pengguna mengetik --}}
                        <script>
                            (function () {
                                const searchInput = document.getElementById('mk-student-filter');
                                const searchForm = document.getElementById('mk-student-search-form');

                                if (!searchInput || !searchForm) return;

                                let searchTimeout;

                                searchInput.addEventListener('input', function () {
                                    clearTimeout(searchTimeout);

                                    searchTimeout = setTimeout(function () {
                                        searchForm.requestSubmit();
                                    }, 400);
                                });
                            })();
                        </script>

                        @if ($available->isEmpty())
                            @if (request()->filled('q'))
                                <p class="mk-desc-text">Mahasiswa yang dicari tidak ditemukan.</p>
                            @else
                                <p class="mk-desc-text">Semua mahasiswa sudah terdaftar pada mata kuliah ini.</p>
                            @endif
                        @else
                            {{-- Form pendaftaran mahasiswa --}}
                            <form
                                action="{{ route($routePrefix . '.mahasiswa.store', $mataKuliah) }}"
                                method="POST"
                                class="mk-enroll-form"
                                id="mk-enroll-form"
                            >
                                @csrf

                                <div class="mk-student-picker" id="mk-student-picker">
                                    @foreach ($available as $s)
                                        <label class="mk-student-option">
                                            <input
                                                type="checkbox"
                                                value="{{ $s->id }}"
                                                @checked(in_array(
                                                    $s->id,
                                                    array_map('intval', (array) old('student_ids', []))
                                                ))
                                            >
                                            <span>{{ $s->name }}</span>
                                            <small>{{ $s->nim_nip }}</small>
                                        </label>
                                    @endforeach
                                </div>

                                {{-- Navigasi pagination mahasiswa --}}
                                @if ($available->hasPages())
                                    <nav class="mk-pagination" aria-label="Navigasi halaman mahasiswa">

                                        {{-- Tombol sebelumnya --}}
                                        @if ($available->onFirstPage())
                                            <span class="mk-page-disabled">Sebelumnya</span>
                                        @else
                                            <a href="{{ $available->previousPageUrl() }}" class="mk-page-link">
                                                Sebelumnya
                                            </a>
                                        @endif

                                        {{-- Nomor halaman --}}
                                        @foreach ($available->getUrlRange(
                                            max(1, $available->currentPage() - 2),
                                            min($available->lastPage(), $available->currentPage() + 2)
                                        ) as $page => $url)
                                            @if ($page == $available->currentPage())
                                                <span class="mk-page-active">{{ $page }}</span>
                                            @else
                                                <a href="{{ $url }}" class="mk-page-link">{{ $page }}</a>
                                            @endif
                                        @endforeach

                                        {{-- Tombol selanjutnya --}}
                                        @if ($available->hasMorePages())
                                            <a href="{{ $available->nextPageUrl() }}" class="mk-page-link">
                                                Selanjutnya
                                            </a>
                                        @else
                                            <span class="mk-page-disabled">Selanjutnya</span>
                                        @endif

                                    </nav>
                                @endif

                                <p class="mk-pagination-info">
                                    Menampilkan {{ $available->firstItem() }}–{{ $available->lastItem() }}
                                    dari {{ $available->total() }} mahasiswa
                                </p>

                                <div class="form-actions">
                                    <button type="submit" class="btn btn-primary">
                                        Tambahkan yang Dipilih
                                    </button>
                                </div>
                            </form>

                            {{-- JavaScript penyimpanan pilihan --}}
                            <script>
                                (function () {
                                    const picker = document.getElementById('mk-student-picker');
                                    const enrollForm = document.getElementById('mk-enroll-form');
                                    const searchForm = document.getElementById('mk-student-search-form');

                                    if (!picker || !enrollForm || !searchForm) return;

                                    const storageKey = 'selected-students-{{ $mataKuliah->id }}';

                                    let selectedIds = new Set(
                                        JSON.parse(sessionStorage.getItem(storageKey) || '[]').map(String)
                                    );

                                    // Gabungkan dengan pilihan lama jika validasi gagal
                                    const oldSelectedIds = @json(
                                        array_map('strval', (array) old('student_ids', []))
                                    );

                                    oldSelectedIds.forEach(id => selectedIds.add(id));

                                    function saveSelectedIds() {
                                        sessionStorage.setItem(
                                            storageKey,
                                            JSON.stringify([...selectedIds])
                                        );
                                    }

                                    // Pulihkan pilihan checkbox ketika halaman dibuka
                                    picker.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
                                        checkbox.checked = selectedIds.has(checkbox.value);

                                        checkbox.addEventListener('change', function () {
                                            if (this.checked) {
                                                selectedIds.add(this.value);
                                            } else {
                                                selectedIds.delete(this.value);
                                            }

                                            saveSelectedIds();
                                        });
                                    });

                                    // Simpan pilihan sebelum pencarian
                                    searchForm.addEventListener('submit', saveSelectedIds);

                                    // Kirim seluruh pilihan dari semua halaman
                                    enrollForm.addEventListener('submit', function () {
                                        picker.querySelectorAll('input[type="checkbox"]:checked')
                                            .forEach(checkbox => selectedIds.add(checkbox.value));

                                        selectedIds.forEach(id => {
                                            const hiddenInput = document.createElement('input');

                                            hiddenInput.type = 'hidden';
                                            hiddenInput.name = 'student_ids[]';
                                            hiddenInput.value = id;

                                            enrollForm.appendChild(hiddenInput);
                                        });

                                        sessionStorage.removeItem(storageKey);
                                    });
                                })();
                            </script>
                        @endif
                    @endif
                </section>
            @endisset

            @if ($canEdit)
                <div class="form-actions" style="margin-top:1.5rem;">
                    <a href="{{ route($routePrefix . '.edit', $mataKuliah) }}" class="btn btn-primary">
                        Edit Mata Kuliah
                    </a>
                </div>
            @endif

        </div>
    </article>

</x-layout>
