<?php

namespace App\Http\Controllers;

use App\Support\CashFlowForecaster;
use Illuminate\Http\Request;

class ForecastController extends Controller
{
    public function index(Request $request)
    {
        $household = $this->household();
        $days = in_array($request->integer('days'), [30, 60, 90]) ? $request->integer('days') : 60;

        $forecast = CashFlowForecaster::project($household, $days);

        return view('forecast.index', [
            'points' => $forecast['points'],
            'events' => $forecast['events'],
            'discretionaryDaily' => $forecast['discretionary_daily'],
            'startBalance' => $forecast['start_balance'],
            'days' => $days,
        ]);
    }
}
