@extends('layouts.guest')
@section('title', 'Set up your household')
@section('content')
<div class="space-y-6">
    <div>
        <h2 class="font-display text-lg font-semibold mb-3">Start a new household</h2>
        <form method="POST" action="{{ route('households.store') }}" class="space-y-3">
            @csrf
            <input type="text" name="name" placeholder="e.g. The Nguyen Household" required class="input">
            <button type="submit" class="btn-primary w-full">Create household</button>
        </form>
    </div>

    <div class="flex items-center gap-3 text-xs text-ink/40">
        <div class="h-px flex-1 bg-moss-100"></div>
        OR
        <div class="h-px flex-1 bg-moss-100"></div>
    </div>

    <div>
        <h2 class="font-display text-lg font-semibold mb-3">Join with an invite code</h2>
        <form method="POST" action="{{ route('households.join') }}" class="space-y-3">
            @csrf
            <input type="text" name="invite_code" placeholder="Invite code" required class="input uppercase">
            <button type="submit" class="btn-secondary w-full">Join household</button>
        </form>
    </div>
</div>
@endsection
