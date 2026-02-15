<?php

use App\Enums\TagType;
use App\Models\Tag;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    #[Computed]
    public function domains(): Collection
    {
        return Tag::ofType(TagType::Domain)
            ->whereIn('status', ['verified', 'pending'])
            ->ordered()
            ->take(8)
            ->get();
    }
};
?>

@placeholder
<div class="bg-slate-50 py-16">
    <div class="container mx-auto px-6 lg:px-12">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            @for ($i = 0; $i < 4; $i++)
                <div class="bg-white rounded-2xl p-6 border border-slate-100">
                    <div class="w-12 h-12 bg-slate-100 rounded-xl animate-pulse mb-4"></div>
                    <div class="h-4 w-20 bg-slate-100 rounded animate-pulse"></div>
                </div>
            @endfor
        </div>
    </div>
</div>
@endplaceholder

<div class="bg-slate-50 py-16">
    <div class="container mx-auto px-6 lg:px-12">
        <div class="text-center mb-10">
            <h2 class="text-2xl font-bold text-slate-900">{{ __('Cari Mengikut Bidang') }}</h2>
            <p class="text-slate-500 mt-2">{{ __('Terokai majlis ilmu mengikut bidang keilmuan' ) }}</p>
        </div>
        
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            @foreach($this->domains as $domain)
                <a href="{{ route('events.index', ['domain' => $domain->slug]) }}" wire:navigate
                    class="group bg-white rounded-2xl p-6 border border-slate-100 hover:border-emerald-200 hover:shadow-lg hover:shadow-emerald-100/50 transition-all">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-emerald-100 to-teal-100 flex items-center justify-center mb-4 group-hover:from-emerald-200 group-hover:to-teal-200 transition-colors">
                        <svg class="w-6 h-6 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                        </svg>
                    </div>
                    <h3 class="font-semibold text-slate-900 group-hover:text-emerald-600 transition-colors">
                        {{ $domain->name }}
                    </h3>
                </a>
            @endforeach
        </div>
        
        <!-- Additional Quick Links -->
        <div class="flex flex-wrap justify-center gap-3 mt-8">
            <a href="{{ route('events.index', ['search' => 'Tazkirah']) }}" wire:navigate
                class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white border border-slate-200 text-slate-600 text-sm font-medium hover:border-emerald-400 hover:text-emerald-600 transition-all">
                #Tazkirah
            </a>
            <a href="{{ route('events.index', ['search' => 'Tafsir']) }}" wire:navigate
                class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white border border-slate-200 text-slate-600 text-sm font-medium hover:border-emerald-400 hover:text-emerald-600 transition-all">
                #Tafsir
            </a>
            <a href="{{ route('events.index', ['search' => 'Fiqh']) }}" wire:navigate
                class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white border border-slate-200 text-slate-600 text-sm font-medium hover:border-emerald-400 hover:text-emerald-600 transition-all">
                #Fiqh
            </a>
            <a href="{{ route('events.index', ['search' => 'Aqidah']) }}" wire:navigate
                class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white border border-slate-200 text-slate-600 text-sm font-medium hover:border-emerald-400 hover:text-emerald-600 transition-all">
                #Aqidah
            </a>
            <a href="{{ route('events.index', ['search' => 'Sirah']) }}" wire:navigate
                class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white border border-slate-200 text-slate-600 text-sm font-medium hover:border-emerald-400 hover:text-emerald-600 transition-all">
                #Sirah
            </a>
        </div>
    </div>
</div>
