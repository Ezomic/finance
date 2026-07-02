<?php

namespace App\Http\Controllers;

abstract class Controller
{
    /**
     * The household the current user is working in. Every controller that
     * touches household-scoped data resolves it through here.
     */
    protected function household()
    {
        return auth()->user()->currentHousehold;
    }

    protected function abortUnlessOwned($model): void
    {
        if ($model->household_id !== $this->household()->id) {
            abort(403);
        }
    }
}
