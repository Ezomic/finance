<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Builds the cache key for the "login" named rate limiter (registered in
 * AppServiceProvider), so AuthController can clear the right entry on a
 * successful login. Laravel's throttle middleware hashes named-limiter keys
 * as md5($limiterName.$rawKey) by default (ThrottleRequests::$shouldHashKeys)
 * — RateLimiter::clear() has to reproduce that exact transform, or it clears
 * a key nothing ever wrote to.
 */
class LoginThrottle
{
    public static function rawKey(Request $request): string
    {
        return strtolower((string) $request->input('email')).'|'.$request->ip();
    }

    public static function cacheKey(Request $request): string
    {
        return md5('login'.self::rawKey($request));
    }
}
