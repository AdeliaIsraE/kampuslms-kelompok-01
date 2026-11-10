<x-layout title="Edit Pengguna">

    <div class="admin-form-page">

        <header class="admin-form-heading">
            <p class="admin-form-eyebrow">ADMINISTRATOR / KELOLA USER</p>
            <h1>Edit Pengguna</h1>
            <p>Perbarui informasi akun pengguna di dalam sistem KampusLMS.</p>
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
                    <p>Ubah informasi berikut untuk memperbarui akun ini.</p>
                </div>
                <span class="admin-form-tag">EDIT USER</span>
            </div>

            <form
                action="{{ route('admin.users.update', $user) }}"
                method="POST"
                autocomplete="off"
                class="admin-form-grid"
            >
                @csrf
                @method('PUT')

                {{-- Nama --}}
                <div class="form-group">
                    <label for="name" class="form-label">
                        Nama Lengkap
                    </label>

                    <input
                        type="text"
                        name="name"
                        id="name"
                        value="{{ old('name', $user->name) }}"
                        maxlength="150"
                        class="form-control"
                        placeholder="Masukkan nama lengkap"
                    >
                </div>

                {{-- Email --}}
                <div class="form-group">
                    <label for="email" class="form-label">
                        Email
                    </label>

                    <input
                        type="email"
                        name="email"
                        id="email"
                        value="{{ old('email', $user->email) }}"
                        maxlength="150"
                        autocomplete="off"
                        class="form-control"
                        placeholder="contoh@email.com"
                    >
                </div>

                {{-- Password (opsional) --}}
                <div class="form-group">
                    <label for="password" class="form-label">
                        Kata Sandi Baru
                    </label>

                    <x-password-input
                        name="password"
                        placeholder="Kosongkan jika tidak ingin mengubah"
                    />

                    <small class="form-hint">
                        Biarkan kosong jika tidak ingin mengganti kata sandi.
                    </small>
                </div>

                {{-- Konfirmasi Password --}}
                <div class="form-group">
                    <label for="password_confirmation" class="form-label">
                        Konfirmasi Kata Sandi Baru
                    </label>

                    <x-password-input name="password_confirmation" />
                </div>

                {{-- Role --}}
                <div class="form-group">
                    <label for="role" class="form-label">
                        Peran
                    </label>

                    <select name="role" id="role" class="form-control">
                        <option value="">-- Pilih Peran --</option>
                        <option value="admin" {{ old('role', $user->role) === 'admin' ? 'selected' : '' }}>Admin</option>
                        <option value="dosen" {{ old('role', $user->role) === 'dosen' ? 'selected' : '' }}>Dosen</option>
                        <option value="mahasiswa" {{ old('role', $user->role) === 'mahasiswa' ? 'selected' : '' }}>Mahasiswa</option>
                    </select>

                    @if ($user->is(auth()->user()))
                        <small class="form-hint">
                            Anda sedang mengedit akun Anda sendiri, jadi peran tidak dapat diubah.
                        </small>
                    @endif
                </div>

                {{-- NIM / NIP --}}
                <div class="form-group admin-form-field--full">
                    <label for="nim_nip" class="form-label">
                        NIM / NIP
                    </label>

                    <input
                        type="text"
                        name="nim_nip"
                        id="nim_nip"
                        value="{{ old('nim_nip', $user->nim_nip) }}"
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
                        Simpan Perubahan
                    </button>

                    <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">
                        Batal
                    </a>

                </div>

            </form>

        </section>

    </div>

</x-layout>