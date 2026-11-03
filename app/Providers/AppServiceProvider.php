<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Minggu 11: lazy loading (potensi N+1) menjadi exception di lingkungan
        // non-produksi. Di produksi tetap berjalan agar pengguna tidak melihat
        // halaman rusak.
        Model::preventLazyLoading(! $this->app->isProduction());

        // 429 dalam JSON lengkap dengan header Retry-After
        $tooMany = fn (string $pesan) => function (Request $request, array $headers) use ($pesan) {
            return response()->json(['message' => $pesan], 429, $headers);
        };

        // Umum: 60/menit. Kunci: id pengguna (dari token), cadangan IP.
        RateLimiter::for('api-umum', function (Request $request) use ($tooMany) {
            return Limit::perMinute(60)
                ->by($request->user()?->id ?: $request->ip())
                ->response($tooMany('Terlalu banyak permintaan. Coba lagi sebentar lagi.'));
        });

        // Login: 5/menit per kombinasi email+IP, plus 20/menit per IP.
        RateLimiter::for('api-login', function (Request $request) use ($tooMany) {
            $email = $request->input('email');
            $email = is_string($email) ? Str::lower($email) : ''; // input bisa berupa array
            $pesan = $tooMany('Terlalu banyak percobaan login. Coba lagi dalam satu menit.');

            return [
                Limit::perMinute(5)->by($email . '|' . $request->ip())->response($pesan),
                Limit::perMinute(20)->by($request->ip())->response($pesan),
            ];
        });
    }
}