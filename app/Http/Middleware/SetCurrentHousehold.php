<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentHousehold
{
    /**
     * Make sure a logged-in user always has a valid "current household" to work in.
     * If they belong to none, send them to create/join one before anything else.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $onboarding = $request->routeIs('households.*') || $request->routeIs('logout');

            if (! $user->current_household_id || ! $user->households()->where('households.id', $user->current_household_id)->exists()) {
                $first = $user->households()->first();

                if ($first) {
                    $user->forceFill(['current_household_id' => $first->id])->save();
                } elseif (! $onboarding) {
                    return redirect()->route('households.create');
                }
            }
        }

        return $next($request);
    }
}
