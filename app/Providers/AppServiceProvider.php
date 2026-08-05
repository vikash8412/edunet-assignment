<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
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
        Vite::prefetch(concurrency: 3);

        // Public fill endpoint: spam guard, per IP per form.
        RateLimiter::for('fill', function (Request $request) {
            return Limit::perMinute(10)
                ->by($request->ip().'|'.$request->route('publicId'));
        });

        // Analytics beacons are chattier but cheap.
        RateLimiter::for('fill-events', function (Request $request) {
            return Limit::perMinute(30)
                ->by($request->ip().'|'.$request->route('publicId'));
        });
    }
}
