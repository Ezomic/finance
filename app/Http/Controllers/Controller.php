<?php

namespace App\Http\Controllers;

use App\Models\Household;
use Illuminate\Database\Eloquent\Model;

abstract class Controller
{
    /**
     * The household the current user is working in. Every controller that
     * touches household-scoped data resolves it through here.
     */
    protected function household(): Household
    {
        /** @var Household $household */
        $household = auth()->user()->currentHousehold;

        return $household;
    }

    protected function abortUnlessOwned(Model $model): void
    {
        if ($model->getAttribute('household_id') !== $this->household()->id) {
            abort(403);
        }
    }
}
