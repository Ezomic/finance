@extends('layouts.guest')
@section('title', 'Create account')
@section('content')
<form method="POST" action="{{ route('register') }}" class="space-y-4">
    @csrf
    <div>
        <label class="label">Name</label>
        <input type="text" name="name" value="{{ old('name') }}" required autofocus class="input">
    </div>
    <div>
        <label class="label">Email</label>
        <input type="email" name="email" value="{{ old('email') }}" required class="input">
    </div>
    <div>
        <label class="label">Password</label>
        <input type="password" name="password" required class="input">
    </div>
    <div>
        <label class="label">Confirm password</label>
        <input type="password" name="password_confirmation" required class="input">
    </div>
    <button type="submit" class="btn-primary w-full">Create account</button>
</form>
<p class="text-sm text-center text-ink/60 mt-6">
    Already have an account? <a href="{{ route('login') }}" class="text-moss-700 font-medium hover:underline">Sign in</a>
</p>
@endsection
