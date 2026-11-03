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
        // $available hanya ada bila route tambah mahasiswa tersedia (admin).
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

            {{-- Pintasan ke materi & tugas (hanya bila route-nya ada untuk peran ini) --}}
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

                    {{-- Form tambah mahasiswa (hanya admin) --}}
                    @if ($canEnroll)
                        <h3 class="mk-enroll-subtitle">Tambah Mahasiswa</h3>

                        @if ($available->isEmpty())
                            <p class="mk-desc-text">Semua mahasiswa sudah terdaftar pada mata kuliah ini.</p>
                        @else
                            <form action="{{ route($routePrefix . '.mahasiswa.store', $mataKuliah) }}"
                                  method="POST" class="mk-enroll-form">
                                @csrf

                                <input
                                    type="search"
                                    id="mk-student-filter"
                                    class="form-control"
                                    placeholder="Cari nama atau NIM..."
                                    autocomplete="off"
                                >

                                <div class="mk-student-picker" id="mk-student-picker">
                                    @foreach ($available as $s)
                                        <label class="mk-student-option"
                                               data-search="{{ \Illuminate\Support\Str::lower($s->name . ' ' . $s->nim_nip) }}">
                                            <input
                                                type="checkbox"
                                                name="student_ids[]"
                                                value="{{ $s->id }}"
                                                @checked(in_array($s->id, array_map('intval', (array) old('student_ids', []))))
                                            >
                                            <span>{{ $s->name }}</span>
                                            <small>{{ $s->nim_nip }}</small>
                                        </label>
                                    @endforeach
                                </div>

                                <div class="form-actions">
                                    <button type="submit" class="btn btn-primary">
                                        Tambahkan yang Dipilih
                                    </button>
                                </div>
                            </form>

                            <script>
                                (function () {
                                    const input  = document.getElementById('mk-student-filter');
                                    const picker = document.getElementById('mk-student-picker');
                                    if (!input || !picker) return;

                                    input.addEventListener('input', function () {
                                        const q = input.value.trim().toLowerCase();
                                        picker.querySelectorAll('.mk-student-option').forEach(function (el) {
                                            el.hidden = q !== '' && !el.dataset.search.includes(q);
                                        });
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