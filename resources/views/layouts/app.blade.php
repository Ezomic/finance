<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600;9..144,700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-paper">
    <div class="flex min-h-screen">
        <aside class="hidden md:flex w-64 flex-col border-r border-moss-100 bg-white/60 px-6 py-8">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-2 mb-10">
                <span class="text-2xl">🌿</span>
                <span class="font-display text-lg font-semibold text-moss-900 leading-tight">{{ config('app.name') }}</span>
            </a>

            <nav class="flex flex-col gap-1 text-sm">
                @php
                    $links = [
                        ['route' => 'dashboard', 'label' => 'Overview', 'icon' => '◆'],
                        ['route' => 'accounts.index', 'label' => 'Accounts', 'icon' => '▤'],
                        ['route' => 'transactions.index', 'label' => 'Transactions', 'icon' => '≡'],
                        ['route' => 'categorize.index', 'label' => 'Categorize', 'icon' => '✓'],
                        ['route' => 'budgets.index', 'label' => 'Budgets', 'icon' => '◐'],
                        ['route' => 'bills.index', 'label' => 'Bills', 'icon' => '⏲'],
                        ['route' => 'subscriptions.index', 'label' => 'Subscriptions', 'icon' => '↻'],
                        ['route' => 'reports.index', 'label' => 'Reports', 'icon' => '▲'],
                        ['route' => 'forecast.index', 'label' => 'Forecast', 'icon' => '⇢'],
                        ['route' => 'categories.index', 'label' => 'Categories', 'icon' => '◈'],
                        ['route' => 'import.index', 'label' => 'Import', 'icon' => '↑'],
                        ['route' => 'activity.index', 'label' => 'Activity', 'icon' => '⏱'],
                    ];
                @endphp
                @foreach ($links as $link)
                    <a href="{{ route($link['route']) }}"
                       class="flex items-center gap-3 rounded-xl px-3 py-2 transition-colors {{ request()->routeIs($link['route']) || request()->routeIs(str($link['route'])->before('.').'.*') ? 'bg-moss-100 text-moss-900 font-medium' : 'text-ink/70 hover:bg-moss-50' }}">
                        <span class="w-4 text-center text-moss-600">{{ $link['icon'] }}</span>
                        {{ $link['label'] }}
                    </a>
                @endforeach
            </nav>

            <div class="mt-auto pt-6 border-t border-moss-100">
                @auth
                    <div class="text-xs text-ink/50 mb-2">Signed in as</div>
                    <div class="text-sm font-medium mb-3">{{ auth()->user()->name }}</div>
                    <a href="{{ route('households.settings') }}" class="text-xs text-moss-700 hover:underline">Household settings</a>
                    <form method="POST" action="{{ route('logout') }}" class="mt-3">
                        @csrf
                        <button class="text-xs text-clay hover:underline">Sign out</button>
                    </form>
                @endauth
            </div>
        </aside>

        <div class="flex-1 flex flex-col">
            <header class="md:hidden flex items-center justify-between border-b border-moss-100 bg-white px-4 py-3">
                <a href="{{ route('dashboard') }}" class="font-display text-lg font-semibold text-moss-900">🌿 {{ config('app.name') }}</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="text-xs text-clay">Sign out</button>
                </form>
            </header>

            <nav class="md:hidden flex overflow-x-auto gap-2 border-b border-moss-100 bg-white px-4 py-2 text-xs">
                <a href="{{ route('dashboard') }}" class="px-3 py-1 rounded-full {{ request()->routeIs('dashboard') ? 'bg-moss-100' : '' }}">Overview</a>
                <a href="{{ route('accounts.index') }}" class="px-3 py-1 rounded-full {{ request()->routeIs('accounts.*') ? 'bg-moss-100' : '' }}">Accounts</a>
                <a href="{{ route('transactions.index') }}" class="px-3 py-1 rounded-full {{ request()->routeIs('transactions.*') ? 'bg-moss-100' : '' }}">Transactions</a>
                <a href="{{ route('budgets.index') }}" class="px-3 py-1 rounded-full {{ request()->routeIs('budgets.*') ? 'bg-moss-100' : '' }}">Budgets</a>
                <a href="{{ route('bills.index') }}" class="px-3 py-1 rounded-full {{ request()->routeIs('bills.*') ? 'bg-moss-100' : '' }}">Bills</a>
                <a href="{{ route('reports.index') }}" class="px-3 py-1 rounded-full {{ request()->routeIs('reports.*') ? 'bg-moss-100' : '' }}">Reports</a>
            </nav>

            <main class="flex-1 px-4 py-6 md:px-10 md:py-10">
                @if (session('status'))
                    <div class="mb-6 rounded-xl border border-moss-300 bg-moss-50 px-4 py-3 text-sm text-moss-700">
                        {{ session('status') }}
                    </div>
                @endif
                @if ($errors->any())
                    <div class="mb-6 rounded-xl border border-clay/40 bg-clay/5 px-4 py-3 text-sm text-clay">
                        <ul class="list-disc list-inside space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="mb-8">
                    <h1 class="font-display text-2xl md:text-3xl font-semibold text-ink">@yield('title', 'Overview')</h1>
                    @hasSection('subtitle')
                        <p class="text-sm text-ink/60 mt-1">@yield('subtitle')</p>
                    @endif
                </div>

                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
