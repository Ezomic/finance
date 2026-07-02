@extends('layouts.app')
@section('title', 'Bills')
@section('subtitle', 'Recurring payments and when they land')
@section('content')

<div class="flex items-center justify-between mb-6">
    <div class="flex gap-2 text-sm">
        <a href="{{ route('bills.index') }}" class="px-3 py-1.5 rounded-full text-ink/60 hover:bg-moss-50">List</a>
        <span class="px-3 py-1.5 rounded-full bg-moss-100 text-moss-900 font-medium">Calendar</span>
    </div>
    <div class="flex items-center gap-3 text-sm">
        <a href="{{ route('bills.calendar', ['month' => $month->copy()->subMonth()->format('Y-m')]) }}" class="text-moss-700 hover:underline">&larr; Prev</a>
        <span class="font-display font-semibold">{{ $month->format('F Y') }}</span>
        <a href="{{ route('bills.calendar', ['month' => $month->copy()->addMonth()->format('Y-m')]) }}" class="text-moss-700 hover:underline">Next &rarr;</a>
    </div>
</div>

<div class="card overflow-hidden">
    <div class="grid grid-cols-7 bg-moss-50 text-xs uppercase tracking-wide text-ink/50">
        @foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $label)
            <div class="px-3 py-2 text-center">{{ $label }}</div>
        @endforeach
    </div>
    <div class="grid grid-cols-7">
        @for ($i = 0; $i < $leadingBlanks; $i++)
            <div class="min-h-[90px] border-t border-r border-moss-100"></div>
        @endfor
        @foreach ($days as $day)
            <div class="min-h-[90px] border-t border-r border-moss-100 p-2">
                <div class="text-xs text-ink/40 mb-1">{{ $day['date']->format('j') }}</div>
                <div class="space-y-1">
                    @foreach ($day['occurrences'] as $occurrence)
                        <div class="text-xs px-1.5 py-1 rounded-lg {{ $occurrence['paid'] ? 'bg-moss-50 text-moss-700' : 'bg-clay/10 text-clay' }}" title="{{ $occurrence['bill']->name }} · {{ number_format($occurrence['bill']->amount, 2) }}">
                            {{ \Illuminate\Support\Str::limit($occurrence['bill']->name, 14) }}
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>

<div class="mt-4 flex gap-4 text-xs text-ink/50">
    <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-clay/10 border border-clay/40 inline-block"></span> Due</span>
    <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-moss-50 border border-moss-300 inline-block"></span> Paid</span>
</div>
@endsection
