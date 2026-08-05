<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreApiTokenRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Laravel\Sanctum\PersonalAccessToken;

class ApiTokenController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return view('settings.api-tokens', [
            'tokens' => $user->tokens()
                ->latest()
                ->get()
                ->map(fn (PersonalAccessToken $token): array => [
                    'id' => $token->getKey(),
                    'name' => $token->name,
                    'createdAtDiff' => $token->created_at?->diffForHumans(),
                    'lastUsedAtDiff' => $token->last_used_at?->diffForHumans(),
                ])
                ->all(),
        ]);
    }

    public function store(StoreApiTokenRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $token = $user->createToken($request->string('name')->toString());

        // Flashed once, rendered on the next load, then gone.
        return redirect()->route('api-tokens.index')
            ->with('status', 'API token created.')
            ->with('createdToken', [
                'name' => $token->accessToken->name,
                'plainText' => $token->plainTextToken,
            ]);
    }

    public function destroy(Request $request, string $token): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        // Scoped to the acting user's own tokens: another user's id deletes nothing.
        $user->tokens()->whereKey($token)->delete();

        return redirect()->route('api-tokens.index')->with('status', 'API token revoked.');
    }
}
