<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Login - Kampus LMS</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

        {{-- Tema terang/gelap: diterapkan sebelum halaman tampil agar tidak berkedip. --}}
        <meta name="color-scheme" content="light dark">
        <script>
            (function () {
                var t = null;
                try { t = localStorage.getItem('tema'); } catch (e) {}
                if (t !== 'light' && t !== 'dark') {
                    t = window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                }
                document.documentElement.setAttribute('data-theme', t);
            })();
        </script>

        {{-- Style halaman login dipisah ke resources/css/welcome.css --}}
        @vite(['resources/css/welcome.css'])
    </head>
    <body class="login-body">

        {{-- Tombol ganti tema (pojok kanan atas) --}}
        <button type="button" class="login-theme" data-theme-toggle aria-label="Ganti ke mode gelap" title="Ganti tema">
            <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
            <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
        </button>

        <main class="login-shell">
            <div class="login-card">

                {{-- ===== Sisi kiri: logo + sapaan ===== --}}
                <section class="login-aside">
                    <div class="login-logo">
                        <img src="{{ asset('images/logo.png') }}" alt="Logo Kampus LMS">
                    </div>

                    <div class="login-welcome">
                        <p class="login-eyebrow">KAMPUS LMS</p>
                        <h1 class="login-title">Selamat datang!</h1>
                        <p class="login-text">
                            LMS Kampus adalah sistem pembelajaran daring yang membantu dosen dan mahasiswa mengelola mata kuliah, tugas, dan penilaian dalam satu tempat.
                        </p>
                    </div>
                </section>

                {{-- ===== Sisi kanan: form login ===== --}}
                <section class="login-panel">

                    {{-- Pesan sukses (mis. setelah kata sandi berhasil direset) --}}
                    @if (session('success'))
                        <div class="login-alert login-alert--success" role="alert">
                            {{ session('success') }}
                        </div>
                    @endif

                    {{-- Pesan error login --}}
                    @if ($errors->any())
                        <div class="login-alert login-alert--error" role="alert">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('login.store') }}" class="login-form">
                        @csrf

                        <div>
                            <input type="text" name="login" value="{{ old('login') }}" placeholder="NIM / NIP / Email"
                                class="login-input" autocomplete="username">
                        </div>

                        <div class="login-field">
                            <input type="password" name="password" id="login-password" placeholder="Kata Sandi"
                                class="login-input login-input--password" autocomplete="current-password">

                            {{-- Tombol lihat / sembunyikan kata sandi --}}
                            <button type="button" id="toggle-password" class="login-eye"
                                aria-label="Tampilkan kata sandi" aria-pressed="false" title="Tampilkan kata sandi">
                                {{-- mata tertutup: kata sandi sedang disembunyikan --}}
                                <svg class="login-eye__closed" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><path d="M14.12 14.12A3 3 0 1 1 9.88 9.88"/><path d="M1 1l22 22"/></svg>
                                {{-- mata terbuka: kata sandi sedang terlihat --}}
                                <svg class="login-eye__open" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>

                        <button type="submit" class="login-button">
                            Masuk
                        </button>

                        <div class="login-forgot">
                            <a href="{{ route('password.request') }}" class="login-link">
                                Lupa kata sandi?
                            </a>
                        </div>
                    </form>

                    {{-- Tombol ke halaman Tentang --}}
                    <div class="login-social">
                        <a href="{{ Route::has('tentang') ? route('tentang') : '#' }}" aria-label="Tentang" title="Tentang">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5"/><circle cx="12" cy="7.5" r="0.6" fill="currentColor"/></svg>
                        </a>
                    </div>
                </section>

            </div>
        </main>


        <script>
            (function () {
                var input = document.getElementById('login-password');
                var btn   = document.getElementById('toggle-password');
                if (!input || !btn) return;

                btn.addEventListener('click', function () {
                    var tampil = input.type === 'password';
                    input.type = tampil ? 'text' : 'password';

                    var label = tampil ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi';
                    btn.setAttribute('aria-pressed', tampil ? 'true' : 'false');
                    btn.setAttribute('aria-label', label);
                    btn.setAttribute('title', label);
                    input.focus();
                });
            })();
        </script>

        <script>
            (function () {
                var root = document.documentElement;
                function label(b) {
                    var gelap = root.getAttribute('data-theme') === 'dark';
                    b.setAttribute('aria-label', gelap ? 'Ganti ke mode terang' : 'Ganti ke mode gelap');
                    b.setAttribute('aria-pressed', gelap ? 'true' : 'false');
                }
                document.querySelectorAll('[data-theme-toggle]').forEach(function (b) {
                    label(b);
                    b.addEventListener('click', function () {
                        var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
                        root.setAttribute('data-theme', next);
                        try { localStorage.setItem('tema', next); } catch (e) {}
                        document.querySelectorAll('[data-theme-toggle]').forEach(label);
                    });
                });
            })();
        </script>

    </body>
</html>