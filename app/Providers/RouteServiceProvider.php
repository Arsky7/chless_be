<?php
// app/Providers/RouteServiceProvider.php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log; // <-- TAMBAHKAN INI

class RouteServiceProvider extends ServiceProvider
{
    public const HOME = '/home';

    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // TAMBAHKAN LOG INI
        Log::info('RouteServiceProvider boot dipanggil');
        Log::info('API file path: ' . base_path('routes/api.php'));
        Log::info('API file exists: ' . (file_exists(base_path('routes/api.php')) ? 'YES' : 'NO'));

        $this->routes(function () {
            Log::info('Loading web routes');
            Route::middleware('web')
                ->group(base_path('routes/web.php'));

            Log::info('Loading api routes from: ' . base_path('routes/api.php'));
            
            // PAKSA LOAD DENGAN REQUIRE (TAMBAHKAN INI SEMENTARA)
            if (file_exists(base_path('routes/api.php'))) {
                Log::info('Requiring api.php directly');
                require base_path('routes/api.php');
            }
            
            Route::prefix('api')
                ->middleware('api')
                ->group(base_path('routes/api.php'));
        });
    }
}