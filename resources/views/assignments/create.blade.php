
<x-layout title="Tambah Tugas">

    <div class="admin-form-page">

        <a href="{{ route($role . '.mata-kuliah.tugas.index', $course) }}" class="mk-back-link">
            &larr; Kembali ke Daftar Tugas
        </a>

        <div class="admin-form-heading">
            <p class="admin-form-eyebrow">PENGELOLAAN AKADEMIK</p>
            <h1>Tambah Tugas</h1>
            <p>
                Buat tugas baru untuk mata kuliah {{ $course->name }}.
                Lengkapi informasi tugas agar mahasiswa dapat memahami
                instruksi dan batas waktu pengumpulan.
            </p>
        </div>

        @include('assignments._form', [
            'action'    => route($role . '.mata-kuliah.tugas.store', $course),
            'cancelUrl' => route($role . '.mata-kuliah.tugas.index', $course),
        ])

    </div>

</x-layout>
