<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $household = $this->household();

        $query = $household->activityLogs()->with('user')->orderByDesc('created_at');

        if ($request->filled('subject_type')) {
            $query->where('subject_type', $request->string('subject_type'));
        }

        $logs = $query->paginate(30)->withQueryString();
        $subjectTypes = $household->activityLogs()->distinct()->pluck('subject_type');

        return view('activity.index', compact('logs', 'subjectTypes'));
    }
}
