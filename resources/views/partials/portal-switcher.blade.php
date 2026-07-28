@php($portalApps = $portalApps ?? [])
@if (! empty($portalApps))
    <div x-data="{ open: false }" class="relative" @keydown.escape.window="open = false">
        <button
            type="button"
            @click="open = !open"
            class="flex w-full items-center gap-2 rounded-xl px-3 py-2 text-sm text-ink/70 transition-colors hover:bg-moss-50"
            :aria-expanded="open"
            aria-label="Switch app"
        >
            <span class="w-4 text-center text-moss-600">▦</span>
            Switch app
        </button>

        <div
            x-show="open"
            x-cloak
            x-transition
            @click.outside="open = false"
            class="absolute bottom-full left-0 z-50 mb-2 w-64 rounded-xl border border-moss-100 bg-white p-2 shadow-lg"
        >
            <div class="px-2 pb-2 pt-1 text-xs text-ink/50">Your apps</div>
            <div class="grid grid-cols-3 gap-1">
                @foreach ($portalApps as $app)
                    <a
                        @if ($app['current']) aria-current="page" @endif
                        href="{{ $app['current'] ? '#' : $app['launch_url'] }}"
                        @class([
                            'flex flex-col items-center gap-1.5 rounded-lg p-2 text-center transition-colors',
                            'bg-moss-50 cursor-default pointer-events-none' => $app['current'],
                            'hover:bg-moss-50' => ! $app['current'],
                        ])
                    >
                        <span
                            x-data="{ ok: true }"
                            class="flex size-9 items-center justify-center overflow-hidden rounded-lg text-xs font-semibold text-white"
                            style="background-color: {{ $app['accent'] ?? '#6b7280' }}"
                        >
                            <img
                                x-show="ok"
                                src="{{ rtrim($app['launch_url'], '/') }}/favicon.svg"
                                alt=""
                                class="size-full object-contain p-1"
                                x-on:error="ok = false"
                            >
                            <span x-show="! ok" x-cloak>{{ $app['initials'] }}</span>
                        </span>
                        <span class="w-full truncate text-[11px] font-medium text-ink">{{ $app['name'] }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
@endif
