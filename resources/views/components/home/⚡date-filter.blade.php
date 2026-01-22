<?php

use App\Models\Event;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    #[Computed]
    public function upcomingDates(): Collection
    {
        $now = now();
        $startDate = $now->copy()->startOfDay();
        $endDate = $now->copy()->addDays(6)->endOfDay();

        // Single query to get all event counts grouped by date
        $eventCounts = Event::query()
            ->where('status', 'approved')
            ->where('visibility', 'public')
            ->whereBetween('starts_at', [$startDate, $endDate])
            ->selectRaw('DATE(starts_at) as date, COUNT(*) as count')
            ->groupByRaw('DATE(starts_at)')
            ->pluck('count', 'date');

        $dates = collect();

        for ($i = 0; $i < 7; $i++) {
            $date = $now->copy()->addDays($i);
            $dateKey = $date->format('Y-m-d');

            $dates->push([
                'date' => $date,
                'day_name' => $i === 0 ? 'Hari Ini' : ($i === 1 ? 'Esok' : $date->translatedFormat('l')),
                'day_short' => $date->format('d'),
                'month_short' => $date->translatedFormat('M'),
                'count' => $eventCounts[$dateKey] ?? 0,
            ]);
        }

        return $dates;
    }
};
?>

@placeholder
<div class="flex flex-wrap items-center justify-center gap-3">
    @for($i = 0; $i < 7; $i++)
        <div class="flex flex-col items-center p-4 rounded-2xl border-2 border-slate-100 min-w-[100px] animate-pulse">
            <span class="w-12 h-3 bg-slate-200 rounded mb-2"></span>
            <span class="w-8 h-6 bg-slate-200 rounded mb-1"></span>
            <span class="w-8 h-3 bg-slate-200 rounded"></span>
        </div>
    @endfor
</div>
@endplaceholder

<div class="flex flex-wrap items-center justify-center gap-3">
    @foreach($this->upcomingDates as $dateItem)
        <a wire:key="date-{{ $dateItem['date']->format('Y-m-d') }}" href="{{ route('events.index', ['date' => $dateItem['date']->format('Y-m-d')]) }}" wire:navigate
            class="group flex flex-col items-center p-4 rounded-2xl border-2 border-slate-100 hover:border-emerald-500 hover:bg-emerald-50 transition-all min-w-[100px] {{ $loop->first ? 'bg-emerald-50 border-emerald-500' : '' }}">
            <span
                class="text-xs font-semibold text-slate-400 uppercase tracking-wide group-hover:text-emerald-600 {{ $loop->first ? 'text-emerald-600' : '' }}">
                {{ $dateItem['day_name'] }}
            </span>
            <span
                class="text-2xl font-bold text-slate-900 group-hover:text-emerald-600 {{ $loop->first ? 'text-emerald-600' : '' }}">
                {{ $dateItem['day_short'] }}
            </span>
            <span class="text-xs text-slate-400">{{ $dateItem['month_short'] }}</span>
            @if($dateItem['count'] > 0)
                <span
                    class="mt-2 inline-flex items-center justify-center w-6 h-6 rounded-full bg-emerald-100 text-emerald-700 text-xs font-bold">
                    {{ $dateItem['count'] }}
                </span>
            @endif
        </a>
    @endforeach
</div>