
{{-- Form materi (dipakai create & edit). --}}
@php
    $editing = isset($material);
    $type = old('type', $material->type ?? 'file');
@endphp

<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="mk-form admin-form-card">
    @csrf

    @if ($editing)
        @method('PUT')
    @endif

    <div class="admin-form-card__heading">
        <div>
            <h2>Informasi Materi</h2>
            <p>
                {{ $editing
                    ? 'Perbarui informasi dan sumber materi pembelajaran.'
                    : 'Isi informasi berikut untuk menambahkan materi pembelajaran baru.' }}
            </p>
        </div>
        <span class="admin-form-tag">FORM MATERI</span>
    </div>

    @if ($errors->any())
        <div class="mk-form-errors">
            <strong>Periksa kembali isian Anda:</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="admin-form-grid">

        <div class="mk-field admin-form-field--full">
            <label for="title" class="mk-label">Judul Materi</label>
            <input
                type="text"
                id="title"
                name="title"
                class="mk-input"
                placeholder="Masukkan judul materi"
                value="{{ old('title', $material->title ?? '') }}"
                required
            >
        </div>

        <div class="mk-field admin-form-field--full">
            <label for="description" class="mk-label">Deskripsi Materi (Opsional)</label>
            <textarea
                id="description"
                name="description"
                rows="4"
                class="mk-input"
                placeholder="Tuliskan gambaran singkat mengenai materi pembelajaran"
            >{{ old('description', $material->description ?? '') }}</textarea>
            <p class="mk-hint">
                Berikan penjelasan singkat agar mahasiswa mengetahui isi materi yang dibagikan.
            </p>
        </div>

        <div class="mk-field admin-form-field--full">
            <label for="type" class="mk-label">Tipe Materi</label>
            <select id="type" name="type" class="mk-input">
                <option value="file" @selected($type === 'file')>
                    Berkas (unggah file)
                </option>
                <option value="link" @selected($type === 'link')>
                    Tautan luar
                </option>
            </select>
            <p class="mk-hint">
                Pilih apakah materi akan dibagikan dalam bentuk berkas atau tautan dari situs lain.
            </p>
        </div>

        <div class="mk-field admin-form-field--full" id="field-file">
            <label for="file" class="mk-label">Berkas Materi</label>

            @if ($editing && $material->file_path)
                <p class="mk-hint">
                    Berkas saat ini:
                    <a href="{{ $material->url }}">{{ $material->original_name }}</a>.
                    Pilih berkas baru hanya jika ingin menggantinya.
                </p>
            @endif

            <input
                type="file"
                id="file"
                name="file"
                class="mk-input"
            >

            <p class="mk-hint">
                Format yang didukung: PDF, Word, PowerPoint, Excel, ZIP, TXT, JPG, dan PNG. Maksimal 20 MB.
            </p>
        </div>

        <div class="mk-field admin-form-field--full" id="field-link">
            <label for="external_url" class="mk-label">Tautan Materi</label>
            <input
                type="url"
                id="external_url"
                name="external_url"
                class="mk-input"
                placeholder="https://contoh.com/materi"
                value="{{ old('external_url', $material->external_url ?? '') }}"
            >
            <p class="mk-hint">
                Masukkan alamat URL materi yang ingin dibagikan kepada mahasiswa.
            </p>
        </div>

    </div>

    <div class="mk-form-actions admin-form-actions">
        <button type="submit" class="mk-btn-primary">
            {{ $editing ? 'Simpan Perubahan' : 'Simpan Materi' }}
        </button>

        <a href="{{ $cancelUrl }}" class="mk-btn-ghost">
            Batal
        </a>
    </div>

</form>

<script>
    (function () {
        var type = document.getElementById('type');
        var fileField = document.getElementById('field-file');
        var linkField = document.getElementById('field-link');

        function toggle() {
            var isFile = type.value === 'file';
            fileField.style.display = isFile ? '' : 'none';
            linkField.style.display = isFile ? 'none' : '';
        }

        type.addEventListener('change', toggle);
        toggle();
    })();
</script>
