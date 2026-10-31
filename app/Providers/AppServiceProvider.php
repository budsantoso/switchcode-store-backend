<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        // Rate limiter anti brute-force untuk login: max 5 percobaan per menit per kombinasi email + IP
        RateLimiter::for('login', function (Request $request) {
            $throttleKey = strtolower((string) $request->input('email')) . '|' . $request->ip();

            return Limit::perMinute(5)
                ->by($throttleKey)
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Terlalu banyak percobaan login gagal. Demi keamanan, akun/IP dibatasi selama 1 menit.',
                    ], 429, $headers);
                });
        });

        // Rate limiter anti spam register: max 3 registrasi per jam per IP
        RateLimiter::for('register', function (Request $request) {
            return Limit::perHour(5)
                ->by($request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Terlalu banyak permintaan registrasi. Silakan coba lagi nanti.',
                    ], 429, $headers);
                });
        });

        // Global API limiter: max 60 request per menit per IP
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });
    }
}
