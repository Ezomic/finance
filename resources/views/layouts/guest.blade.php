<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-paper flex items-center justify-center px-4">
    <div class="w-full max-w-sm">
        <div class="text-center mb-8">
            <span class="text-3xl">🌿</span>
            <h1 class="font-display text-2xl font-semibold text-moss-900 mt-2">{{ config('app.name') }}</h1>
            <p class="text-sm text-ink/60 mt-1">A calm place to keep the household's books.</p>
        </div>

        <div class="card p-6">
            @if (session('status'))
                <div class="mb-4 rounded-xl border border-moss-300 bg-moss-50 px-4 py-3 text-sm text-moss-700">
                    {{ session('status') }}
                </div>
            @endif
            @if ($errors->any())
                <div class="mb-4 rounded-xl border border-clay/40 bg-clay/5 px-4 py-3 text-sm text-clay">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @yield('content')
        </div>
    </div>
</body>
</html>
