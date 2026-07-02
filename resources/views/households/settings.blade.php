@extends('layouts.app')
@section('title', 'Household settings')
@section('content')
<div class="grid gap-6 md:grid-cols-2">
    <div class="card p-6">
        <h2 class="font-display text-lg font-semibold mb-1">{{ $household->name }}</h2>
        <p class="text-sm text-ink/60 mb-4">Currency: {{ $household->currency }}</p>

        <div class="rounded-xl bg-moss-50 border border-moss-100 px-4 py-3">
            <div class="text-xs uppercase tracking-wide text-ink/50 mb-1">Invite code</div>
            <div class="font-display text-xl tracking-widest text-moss-800">{{ $household->invite_code }}</div>
            <p class="text-xs text-ink/50 mt-1">Share this so others can join your household from the "Join with an invite code" screen.</p>
        </div>
    </div>

    <div class="card p-6">
        <h2 class="font-display text-lg font-semibold mb-4">Members</h2>
        <ul class="space-y-3">
            @foreach ($household->users as $member)
                <li class="flex items-center justify-between text-sm">
                    <span>{{ $member->name }}</span>
                    <span class="text-xs uppercase tracking-wide text-moss-700 bg-moss-50 px-2 py-1 rounded-full">{{ $member->pivot->role }}</span>
                </li>
            @endforeach
        </ul>
    </div>
</div>
@endsection
