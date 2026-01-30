<?php

use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public string $activeTab = 'going'; // going, saved, interested

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['going', 'saved', 'interested'])) {
            $this->activeTab = $tab;
        }
    }

    #[Computed]
    public function events(): Collection
    {
        $user = auth()->user();

        if (!$user) {
            return new Collection();
        }

        return match ($this->activeTab) {
            'saved' => $user->savedEvents()->with(['institution', 'venue'])->where('starts_at', '>=', now())->orderBy('starts_at')->take(4)->get(),
            'interested' => $user->interestedEvents()->with(['institution', 'venue'])->where('starts_at', '>=', now())->orderBy('starts_at')->take(4)->get(),
            default => $user->goingEvents()->with(['institution', 'venue'])->where('starts_at', '>=', now())->orderBy('starts_at')->take(4)->get(),
        };
    }

    #[Computed]
    public function counts(): array
    {
        $user = auth()->user();
        if (!$user)
            return ['going' => 0, 'saved' => 0, 'interested' => 0];

        return [
            'going' => $user->goingEvents()->where('starts_at', '>=', now())->count(),
            'saved' => $user->savedEvents()->where('starts_at', '>=', now())->count(),
            'interested' => $user->interestedEvents()->where('starts_at', '>=', now())->count(),
        ];
    }
};
?>

<div>
    @auth
        <section class="py-12 bg-slate-50 border-y border-slate-200">
            <div class="container mx-auto px-6 lg:px-12">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-8">
                    <div>
                        <h2 class="font-heading text-2xl font-bold text-slate-900">{{ __('Majlis Anda') }}</h2>
                        <p class="text-slate-500 text-sm">{{ __('Pantau aktiviti dan majlis yang anda minati') }}</p>
                    </div>

                    <div class="flex p-1 bg-white rounded-xl border border-slate-200 shadow-sm">
                        <button wire:click="setTab('going')"
                            class="px-4 py-2 rounded-lg text-sm font-medium transition-all {{ $activeTab === 'going' ? 'bg-emerald-100 text-emerald-700 shadow-sm' : 'text-slate-500 hover:bg-slate-50' }}">
                            {{ __('Menghadiri') }} <span
                                class="ml-1 text-xs opacity-70 bg-current px-1.5 py-0.5 rounded-full text-white">{{ $this->counts['going'] }}</span>
                        </button>
                        <button wire:click="setTab('saved')"
                            class="px-4 py-2 rounded-lg text-sm font-medium transition-all {{ $activeTab === 'saved' ? 'bg-emerald-100 text-emerald-700 shadow-sm' : 'text-slate-500 hover:bg-slate-50' }}">
                            {{ __('Disimpan') }} <span
                                class="ml-1 text-xs opacity-70 bg-current px-1.5 py-0.5 rounded-full text-white">{{ $this->counts['saved'] }}</span>
                        </button>
                        <button wire:click="setTab('interested')"
                            class="px-4 py-2 rounded-lg text-sm font-medium transition-all {{ $activeTab === 'interested' ? 'bg-emerald-100 text-emerald-700 shadow-sm' : 'text-slate-500 hover:bg-slate-50' }}">
                            {{ __('Minat') }} <span
                                class="ml-1 text-xs opacity-70 bg-current px-1.5 py-0.5 rounded-full text-white">{{ $this->counts['interested'] }}</span>
                        </button>
                    </div>
                </div>

                @if($this->events->isEmpty())
                    <div class="text-center py-12 bg-white rounded-2xl border border-slate-200 border-dashed">
                        <p class="text-slate-400 font-medium">{{ __('Tiada majlis dalam senarai ini.') }}</p>
                        <a href="{{ route('events.index') }}"
                            class="text-emerald-600 hover:underline text-sm mt-2 inline-block">{{ __('Cari Majlis') }}</a>
                    </div>
                @else
                    <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
                        @foreach($this->events as $event)
                            <a href="{{ route('events.show', $event) }}" wire:navigate
                                class="block bg-white rounded-xl border border-slate-200 hover:border-emerald-300 hover:shadow-md transition-all p-4 group">
                                <div class="flex items-center gap-2 mb-3 text-xs text-slate-500">
                                    <span class="font-bold text-emerald-600">{{ $event->starts_at?->format('d M') }}</span>
                                    <span>•</span>
                                    <span>{{ $event->starts_at?->format('h:i A') }}</span>
                                </div>
                                <h3
                                    class="font-bold text-slate-900 group-hover:text-emerald-700 transition-colors line-clamp-2 mb-2 min-h-[3rem]">
                                    {{ $event->title }}
                                </h3>
                                <div class="flex items-center gap-2 text-xs text-slate-500">
                                    <span
                                        class="truncate max-w-full">{{ $event->venue?->name ?? $event->institution?->name ?? 'Online' }}</span>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
    @endauth
</div>