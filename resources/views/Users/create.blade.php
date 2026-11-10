
<x-layout title="Tambah Pengguna">

    <div class="admin-form-page">

        <header class="admin-form-heading">
            <p class="admin-form-eyebrow">ADMINISTRATOR / KELOLA USER</p>
            <h1>Tambah Pengguna</h1>
            <p>Tambahkan akun pengguna baru ke dalam sistem KampusLMS.</p>
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
                    <h2>Informasi Pengguna</h2>
                    <p>Lengkapi informasi berikut untuk membuat akun baru.</p>
                </div>
                <span class="admin-form-tag">USER BARU</span>
            </div>

            <form
                action="{{ route('admin.users.store') }}"
                method="POST"
                autocomplete="off"
                class="admin-form-grid"
            >
                @csrf

                <div class="form-group">
                    <label for="name" class="form-label">
                        Nama Lengkap
                    </label>

                    <input
                        type="text"
                        name="name"
                        id="name"
                        value="{{ old('name') }}"
                        maxlength="150"
                        class="form-control"
                        placeholder="Masukkan nama lengkap"
                    >
                </div>

                <div class="form-group">
                    <label for="email" class="form-label">
                        Email
                    </label>

                    <input
                        type="email"
                        name="email"
                        id="email"
                        value="{{ old('email') }}"
                        maxlength="150"
                        autocomplete="off"
                        class="form-control"
                        placeholder="contoh@email.com"
                    >
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">
                        Kata Sandi
                    </label>

                    <x-password-input name="password" />
                </div>

                <div class="form-group">
                    <label for="password_confirmation" class="form-label">
                        Konfirmasi Kata Sandi
                    </label>

                    <x-password-input name="password_confirmation" />
                </div>

                <div class="form-group">
                    <label for="role" class="form-label">
                        Peran
                    </label>

                    <select name="role" id="role" class="form-control">
                        <option value="">-- Pilih Peran --</option>
                        <option value="admin" {{ old('role') === 'admin' ? 'selected' : '' }}>Admin</option>
                        <option value="dosen" {{ old('role') === 'dosen' ? 'selected' : '' }}>Dosen</option>
                        <option value="mahasiswa" {{ old('role') === 'mahasiswa' ? 'selected' : '' }}>Mahasiswa</option>
                    </select>
                </div>

                <div class="form-group admin-form-field--full">
                    <label for="nim_nip" class="form-label">
                        NIM / NIP
                    </label>

                    <input
                        type="text"
                        name="nim_nip"
                        id="nim_nip"
                        value="{{ old('nim_nip') }}"
                        maxlength="30"
                        class="form-control"
                        placeholder="Masukkan NIM atau NIP"
                    >

                    <small class="form-hint">
                        Digunakan sebagai nama pengguna saat login dan wajib diisi untuk semua peran.
                    </small>
                </div>

                <div class="form-actions admin-form-actions admin-form-field--full">

                    <button type="submit" class="btn btn-primary">
                        Simpan Pengguna
                    </button>

                    <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">
                        Batal
                    </a>

                </div>

            </form>

        </section>

    </div>

</x-layout>
