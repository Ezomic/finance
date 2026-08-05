@extends('layouts.app')
@section('title', 'API tokens')
@section('content')
<div class="max-w-2xl space-y-6">
    <div class="card p-6">
        <h2 class="font-display text-lg font-semibold mb-1">Create a token</h2>
        <p class="text-sm text-ink/60 mb-4">Personal access tokens authenticate requests to the API. Treat them like passwords.</p>

        @if (session('createdToken'))
            <div class="mb-4 rounded-xl border border-moss-300 bg-moss-50 px-4 py-3">
                <div class="text-sm font-medium text-moss-800">Token "{{ session('createdToken')['name'] }}" created</div>
                <p class="text-xs text-ink/60 mt-1 mb-2">Copy it now. You will not be able to see it again.</p>
                <code class="block overflow-x-auto rounded-lg bg-white border border-moss-100 px-3 py-2 font-mono text-xs">{{ session('createdToken')['plainText'] }}</code>
            </div>
        @endif

        <form method="POST" action="{{ route('api-tokens.store') }}" class="flex items-start gap-3">
            @csrf
            <input type="text" name="name" value="{{ old('name') }}" placeholder="e.g. CI pipeline" autocomplete="off" class="input flex-1">
            <button type="submit" class="btn-primary">Create token</button>
        </form>
    </div>

    <div class="card p-6">
        <h2 class="font-display text-lg font-semibold mb-4">Your tokens</h2>

        @forelse ($tokens as $token)
            <div class="flex items-center justify-between gap-4 border-t border-moss-100 py-3 first:border-t-0 first:pt-0">
                <div class="min-w-0">
                    <div class="text-sm font-medium truncate">{{ $token['name'] }}</div>
                    <div class="text-xs text-ink/50">
                        Created {{ $token['createdAtDiff'] }} ·
                        {{ $token['lastUsedAtDiff'] ? 'last used '.$token['lastUsedAtDiff'] : 'never used' }}
                    </div>
                </div>
                <form method="POST" action="{{ route('api-tokens.destroy', $token['id']) }}"
                      onsubmit="return confirm('Revoke the token &quot;{{ $token['name'] }}&quot;? Anything using it will stop working.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-xs text-clay hover:underline">Revoke</button>
                </form>
            </div>
        @empty
            <p class="text-sm text-ink/60">No API tokens yet. Create one to access the API programmatically.</p>
        @endforelse
    </div>
</div>
@endsection
