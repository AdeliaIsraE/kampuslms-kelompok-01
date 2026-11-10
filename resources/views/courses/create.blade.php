
{{--
    View create: form tambah mata kuliah (hanya admin; lihat CoursePolicy::create).

    Variabel dari CourseController@create:
      $routePrefix -> mis. 'admin.mata-kuliah'
      $lecturers   -> daftar user ber-role dosen (id, name, nim_nip)
--}}
<x-layout title="Tambah Mata Kuliah">

    <div class="admin-form-page">

        <header class="admin-form-heading">
            <p class="admin-form-eyebrow">ADMINISTRATOR / KELOLA MATA KULIAH</p>
            <h1>Tambah Mata Kuliah</h1>
            <p>Tambahkan mata kuliah baru dan tentukan dosen pengampu.</p>
        </header>

        @if ($errors->any())
            <div class="alert alert-error" role="alert">
                <strong>Data belum dapat disimpan.</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="form-card admin-form-card">

            <div class="admin-form-card__heading">
                <div>
                    <h2>Informasi Mata Kuliah</h2>
                    <p>Lengkapi informasi mata kuliah yang akan ditambahkan.</p>
                </div>
                <span class="admin-form-tag">MATA KULIAH BARU</span>
            </div>

            <form
                action="{{ route($routePrefix . '.store') }}"
                method="POST"
                class="admin-form-grid"
            >
                @csrf

                <div class="form-group">
                    <label for="status" class="form-label">
                        Status
                    </label>

                    <select name="status" id="status" class="form-control">
                        <option value="">-- Pilih Status --</option>
                        @foreach (['draft' => 'Draft', 'active' => 'Active', 'archived' => 'Archived'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', 'draft') === $value)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>

                    @error('status')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="code" class="form-label">
                        Kode Mata Kuliah
                    </label>

                    <input
                        type="text"
                        name="code"
                        id="code"
                        value="{{ old('code') }}"
                        maxlength="20"
                        class="form-control"
                        placeholder="Contoh: IF101"
                    >

                    @error('code')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-group admin-form-field--full">
                    <label for="name" class="form-label">
                        Nama Mata Kuliah
                    </label>

                    <input
                        type="text"
                        name="name"
                        id="name"
                        value="{{ old('name') }}"
                        maxlength="150"
                        class="form-control"
                        placeholder="Masukkan nama mata kuliah"
                    >

                    @error('name')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="sks" class="form-label">
                        SKS
                    </label>

                    <input
                        type="number"
                        name="sks"
                        id="sks"
                        min="1"
                        max="6"
                        value="{{ old('sks') }}"
                        class="form-control"
                        placeholder="1 - 6"
                    >

                    @error('sks')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-group">
                    <label for="lecturer_id" class="form-label">
                        Dosen Pengampu
                    </label>

                    <select name="lecturer_id" id="lecturer_id" class="form-control">
                        <option value="">-- Pilih Dosen --</option>
                        @foreach ($lecturers as $lecturer)
                            <option value="{{ $lecturer->id }}" @selected((int) old('lecturer_id') === $lecturer->id)>
                                {{ $lecturer->name }} ({{ $lecturer->nim_nip }})
                            </option>
                        @endforeach
                    </select>

                    @error('lecturer_id')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-group admin-form-field--full">
                    <label for="description" class="form-label">
                        Deskripsi
                    </label>

                    <textarea
                        name="description"
                        id="description"
                        rows="4"
                        class="form-control"
                        placeholder="Tambahkan deskripsi singkat mengenai mata kuliah..."
                    >{{ old('description') }}</textarea>

                    @error('description')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="form-actions admin-form-actions admin-form-field--full">

                    <button type="submit" class="btn btn-primary">
                        Simpan Mata Kuliah
                    </button>

                    <a href="{{ route($routePrefix . '.index') }}" class="btn btn-secondary">
                        Batal
                    </a>

                </div>

            </form>

        </section>

    </div>

</x-layout>
