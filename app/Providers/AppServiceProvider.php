<?php

namespace App\Providers;

use App\Services\Portal\IdPortalClient;
use App\Support\LoginThrottle;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Keyed by email + IP, not IP alone, so one user's lockout can't be
        // triggered by someone else guessing a different account from the
        // same address (and vice versa).
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(LoginThrottle::rawKey($request));
        });

        RateLimiter::for('household-join', function (Request $request) {
            return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip());
        });

        View::composer('layouts.app', function (\Illuminate\View\View $view): void {
            $user = Auth::user();

            $portal = $user === null
                ? ['apps' => [], 'categories' => []]
                : app(IdPortalClient::class)->appsFor($user);

            $view->with([
                'portalApps' => $portal['apps'],
                'portalCategories' => $portal['categories'],
            ]);
        });
    }
}
