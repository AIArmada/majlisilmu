@props([
    'event',
    'showDate' => true,
    'showAbsoluteTime' => true,
])

@php
    use App\Enums\TimingMode;
    use App\Support\Timezone\UserDateTimeFormatter;
    
    $isPrayerRelative = $event->timing_mode === TimingMode::PrayerRelative;
    $prayerDisplayText = $event->prayer_display_text;
    $absoluteTime = UserDateTimeFormatter::format($event->starts_at, 'g:i A');
    $date = UserDateTimeFormatter::translatedFormat($event->starts_at, 'l, j F Y');
@endphp

<div {{ $attributes->merge(['class' => 'event-timing']) }}>
    @if($showDate && $date)
        <div class="text-sm text-slate-600 dark:text-slate-400">
            <span class="inline-flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                {{ $date }}
            </span>
        </div>
    @endif

    <div class="flex items-center gap-2 mt-1">
        @if($isPrayerRelative && $prayerDisplayText)
            {{-- Prayer-relative timing badge --}}
            <span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-sm font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    {{-- Mosque/prayer icon --}}
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                {{ $prayerDisplayText }}
            </span>
            
            @if($showAbsoluteTime && $absoluteTime)
                <span class="text-sm text-slate-500 dark:text-slate-400">
                    (~{{ $absoluteTime }})
                </span>
            @endif
        @else
            {{-- Absolute time display --}}
            <span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-sm font-medium bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-300">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                {{ $absoluteTime ?? 'Waktu tidak ditetapkan' }}
            </span>
        @endif
    </div>
</div>