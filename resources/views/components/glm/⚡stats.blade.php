<?php

use App\Models\Event;
use App\Models\Institution;
use App\Models\Speaker;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    #[Computed]
    public function events(): int
    {
        return Cache::remember('glm.stats.events.upcoming', 300, function () {
            return Event::active()
                ->where('starts_at', '>=', now())
                ->count('id');
        });
    }

    #[Computed]
    public function speakers(): int
    {
        return Cache::remember('glm.stats.speakers.upcoming', 300, function () {
            return Speaker::active()
                ->whereHas('events', function ($query) {
                    $query->active()
                        ->where('starts_at', '>=', now());
                })->count('id');
        });
    }

    #[Computed]
    public function institutions(): int
    {
        return Cache::remember('glm.stats.institutions.upcoming', 300, function () {
            return Institution::active()
                ->whereHas('events', function ($query) {
                    $query->active()
                        ->where('starts_at', '>=', now());
                })->count('id');
        });
    }
};
?>

@placeholder
<div class="bg-white border-y border-slate-100">
    <div class="container mx-auto px-6 lg:px-12 py-8">
        <div class="grid grid-cols-3 gap-8 max-w-3xl mx-auto">
            <div class="text-center">
                <div class="h-8 w-16 bg-slate-100 rounded animate-pulse mx-auto"></div>
                <div class="h-4 w-20 bg-slate-50 rounded animate-pulse mx-auto mt-2"></div>
            </div>
            <div class="text-center">
                <div class="h-8 w-16 bg-slate-100 rounded animate-pulse mx-auto"></div>
                <div class="h-4 w-20 bg-slate-50 rounded animate-pulse mx-auto mt-2"></div>
            </div>
            <div class="text-center">
                <div class="h-8 w-16 bg-slate-100 rounded animate-pulse mx-auto"></div>
                <div class="h-4 w-20 bg-slate-50 rounded animate-pulse mx-auto mt-2"></div>
            </div>
        </div>
    </div>
</div>
@endplaceholder

<div class="bg-white border-y border-slate-100">
    <div class="container mx-auto px-6 lg:px-12 py-8">
        <div class="grid grid-cols-3 gap-8 max-w-3xl mx-auto">
            <!-- Events -->
            <a href="{{ route('events.index') }}" wire:navigate
                class="text-center group">
                <div class="text-3xl sm:text-4xl font-bold text-slate-900 group-hover:text-emerald-600 transition-colors">
                    {{ number_format($this->events) }}
                </div>
                <div class="text-sm text-slate-500 mt-1 group-hover:text-emerald-600/80 transition-colors">
                    {{ __('Majlis Akan Datang') }}
                </div>
            </a>
            
            <!-- Speakers -->
            <a href="{{ route('speakers.index') }}" wire:navigate
                class="text-center group border-x border-slate-100">
                <div class="text-3xl sm:text-4xl font-bold text-slate-900 group-hover:text-emerald-600 transition-colors">
                    {{ number_format($this->speakers) }}
                </div>
                <div class="text-sm text-slate-500 mt-1 group-hover:text-emerald-600/80 transition-colors">
                    {{ __('Penceramah') }}
                </div>
            </a>
            
            <!-- Institutions -->
            <a href="{{ route('institutions.index') }}" wire:navigate
                class="text-center group">
                <div class="text-3xl sm:text-4xl font-bold text-slate-900 group-hover:text-emerald-600 transition-colors">
                    {{ number_format($this->institutions) }}
                </div>
                <div class="text-sm text-slate-500 mt-1 group-hover:text-emerald-600/80 transition-colors">
                    {{ __('Institusi') }}
                </div>
            </a>
        </div>
    </div>
</div>
