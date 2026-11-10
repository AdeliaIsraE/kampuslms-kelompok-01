
<x-layout title="Tambah Materi">

    <div class="admin-form-page">

        <a href="{{ route($role . '.mata-kuliah.materi.index', $course) }}" class="mk-back-link">
            &larr; Kembali ke Daftar Materi
        </a>

        <div class="admin-form-heading">
            <p class="admin-form-eyebrow">PENGELOLAAN AKADEMIK</p>
            <h1>Tambah Materi</h1>
            <p>
                Tambahkan materi pembelajaran untuk mata kuliah {{ $course->name }}.
                Lengkapi informasi dan pilih berkas atau tautan yang ingin
                dibagikan kepada mahasiswa.
            </p>
        </div>

        @include('materials._form', [
            'action'    => route($role . '.mata-kuliah.materi.store', $course),
            'cancelUrl' => route($role . '.mata-kuliah.materi.index', $course),
        ])

    </div>

</x-layout>
