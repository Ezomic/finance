@extends('layouts.app')
@section('title', 'Activity')
@section('subtitle', 'A history of what changed and who changed it')
@section('content')

<div class="flex justify-end mb-6">
    <form method="GET" class="flex gap-2">
        <select name="subject_type" class="input max-w-[200px]" onchange="this.form.submit()">
            <option value="">All types</option>
            @foreach ($subjectTypes as $type)
                <option value="{{ $type }}" @selected(request('subject_type') === $type)>{{ class_basename($type) }}</option>
            @endforeach
        </select>
    </form>
</div>

<div class="card overflow-hidden">
    @forelse ($logs as $log)
        <div class="flex items-start justify-between gap-4 px-4 py-3 border-b border-moss-100 last:border-b-0 text-sm">
            <div>
                <div>{{ $log->summary }}</div>
                <div class="text-xs text-ink/50 mt-0.5">{{ $log->user->name ?? 'System' }} · {{ $log->created_at->format('M j, Y g:ia') }}</div>
            </div>
            <span class="text-xs px-2 py-0.5 rounded-full bg-moss-50 text-moss-700 whitespace-nowrap capitalize">{{ $log->action }}</span>
        </div>
    @empty
        <div class="p-10 text-center text-ink/50 text-sm">No activity recorded yet.</div>
    @endforelse
</div>

<div class="mt-6">{{ $logs->links() }}</div>
@endsection
