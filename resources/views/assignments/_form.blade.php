
{{-- Form tugas (dipakai create & edit). --}}
@php
    $editing = isset($assignment);
    $dueAt = old('due_at', isset($assignment) ? $assignment->due_at?->format('Y-m-d\TH:i') : '');
@endphp

<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="mk-form admin-form-card">
    @csrf

    @if ($editing)
        @method('PUT')
    @endif

    <div class="admin-form-card__heading">
        <div>
            <h2>Informasi Tugas</h2>
            <p>
                {{ $editing
                    ? 'Perbarui informasi tugas sesuai kebutuhan.'
                    : 'Isi informasi berikut untuk membuat tugas baru.' }}
            </p>
        </div>
        <span class="admin-form-tag">FORM TUGAS</span>
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
            <label for="title" class="mk-label">Judul Tugas</label>
            <input
                type="text"
                id="title"
                name="title"
                class="mk-input"
                placeholder="Masukkan judul tugas"
                value="{{ old('title', $assignment->title ?? '') }}"
                required
            >
        </div>

        <div class="mk-field admin-form-field--full">
            <label for="instructions" class="mk-label">Instruksi Tugas</label>
            <textarea
                id="instructions"
                name="instructions"
                rows="6"
                class="mk-input"
                placeholder="Tuliskan instruksi atau ketentuan pengerjaan tugas"
                required
            >{{ old('instructions', $assignment->instructions ?? '') }}</textarea>
            <p class="mk-hint">
                Jelaskan hal-hal yang perlu dikerjakan atau diperhatikan mahasiswa.
            </p>
        </div>

        <div class="mk-field admin-form-field--full">
            <label for="attachment" class="mk-label">Lampiran Soal (Opsional)</label>

            @if ($editing && $assignment->file_path)
                <p class="mk-hint">
                    Lampiran saat ini:
                    <a href="{{ $assignment->attachment_url }}">{{ $assignment->original_name }}</a>.
                    Pilih berkas baru hanya jika ingin menggantinya.
                </p>

                <label class="mk-check">
                    <input
                        type="checkbox"
                        name="remove_attachment"
                        value="1"
                        @checked(old('remove_attachment'))
                    >
                    Hapus lampiran ini
                </label>
            @endif

            <input
                type="file"
                id="attachment"
                name="attachment"
                class="mk-input"
            >

            <p class="mk-hint">
                Format yang didukung: PDF, Word, PowerPoint, Excel, ZIP, TXT, JPG, dan PNG. Maksimal 10 MB.
            </p>
        </div>

        <div class="mk-field">
            <label for="due_at" class="mk-label">Batas Waktu</label>
            <input
                type="datetime-local"
                id="due_at"
                name="due_at"
                class="mk-input"
                value="{{ $dueAt }}"
                required
            >
            <p class="mk-hint">Tentukan tanggal dan waktu terakhir pengumpulan.</p>
        </div>

        <div class="mk-field">
            <label for="max_score" class="mk-label">Nilai Maksimal</label>
            <input
                type="number"
                id="max_score"
                name="max_score"
                class="mk-input"
                min="1"
                max="100"
                value="{{ old('max_score', $assignment->max_score ?? 100) }}"
                required
            >
            <p class="mk-hint">Masukkan nilai maksimal antara 1–100.</p>
        </div>

        <div class="mk-field">
            <label for="status" class="mk-label">Status Tugas</label>
            <select id="status" name="status" class="mk-input">
                @foreach (['draft' => 'Draft (belum terlihat mahasiswa)', 'published' => 'Published (terlihat mahasiswa)'] as $value => $label)
                    <option
                        value="{{ $value }}"
                        @selected(old('status', $assignment->status ?? 'draft') === $value)
                    >
                        {{ $label }}
                    </option>
                @endforeach
            </select>
            <p class="mk-hint">Tentukan apakah tugas langsung ditampilkan kepada mahasiswa.</p>
        </div>

        <div class="mk-field admin-form-field--full">
            <label class="mk-check">
                <input type="hidden" name="allow_late" value="0">
                <input
                    type="checkbox"
                    name="allow_late"
                    value="1"
                    @checked(old('allow_late', $assignment->allow_late ?? true))
                >
                Izinkan pengumpulan terlambat
            </label>
            <p class="mk-hint">
                Centang jika mahasiswa masih diperbolehkan mengumpulkan tugas setelah batas waktu.
            </p>
        </div>

    </div>

    <div class="mk-form-actions admin-form-actions">
        <button type="submit" class="mk-btn-primary">
            {{ $editing ? 'Simpan Perubahan' : 'Simpan Tugas' }}
        </button>

        <a href="{{ $cancelUrl }}" class="mk-btn-ghost">
            Batal
        </a>
    </div>

</form>
