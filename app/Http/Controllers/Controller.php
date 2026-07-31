<?php

namespace App\Http\Controllers;

use App\Models\Household;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

abstract class Controller
{
    /**
     * The household the current user is working in. Every controller that
     * touches household-scoped data resolves it through here.
     */
    protected function household(): Household
    {
        $household = $this->currentUser()->currentHousehold;

        abort_unless($household instanceof Household, 403, 'No household selected.');

        return $household;
    }

    /**
     * The authenticated user, typed. Every caller sits behind the auth
     * middleware, so a null here is a routing mistake rather than a
     * reachable state.
     */
    protected function currentUser(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }

    protected function abortUnlessOwned(Model $model): void
    {
        if ($model->getAttribute('household_id') !== $this->household()->id) {
            abort(403);
        }
    }
}
