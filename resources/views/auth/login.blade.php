@extends('layouts.guest')
@section('title', 'Sign in')
@section('content')
<a href="{{ route('sso.redirect') }}" class="btn-primary w-full block text-center">Sign in with Thijssensoftware</a>

<div class="flex items-center gap-3 text-xs text-ink/50 my-6">
    <div class="flex-1 border-t border-moss-200"></div>
    or
    <div class="flex-1 border-t border-moss-200"></div>
</div>

<form method="POST" action="{{ route('login') }}" class="space-y-4">
    @csrf
    <div>
        <label class="label">Email</label>
        <input type="email" name="email" value="{{ old('email') }}" required autofocus class="input">
    </div>
    <div>
        <label class="label">Password</label>
        <input type="password" name="password" required class="input">
    </div>
    <label class="flex items-center gap-2 text-sm text-ink/70">
        <input type="checkbox" name="remember" class="rounded border-moss-300 text-moss-600">
        Remember me
    </label>
    <button type="submit" class="btn-secondary w-full">Sign in with email &amp; password</button>
</form>
<p class="text-sm text-center text-ink/60 mt-6">
    New here? <a href="{{ route('register') }}" class="text-moss-700 font-medium hover:underline">Create an account</a>
</p>
@endsection
