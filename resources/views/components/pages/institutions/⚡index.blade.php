<?php

use App\Models\Institution;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Title('Institutions - Majlis Ilmu')]
class extends Component
{
    #[Computed]
    public function institutions(): LengthAwarePaginator
    {
        $search = request('search');
        $operator = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        return Institution::query()
            ->where('status', 'verified')
            ->withCount(['events' => function ($query) {
                $query->active();
            }])
            ->with(['address.state', 'media'])
            ->when($search, function ($query, $search) use ($operator) {
                $query->where(function ($q) use ($search, $operator) {
                    $q->where('name', $operator, "%{$search}%")
                        ->orWhere('description', $operator, "%{$search}%");
                });
            })
            ->orderBy('name', 'asc')
            ->paginate(12);
    }
};
?>

@php
    $institutions = $this->institutions;
@endphp

<div class="relative min-h-screen pb-32">
        <!-- Hero Section -->
        <div class="relative pt-24 pb-16 bg-white border-b border-slate-100 overflow-hidden">
             <div class="absolute inset-0 bg-emerald-50/50"></div>
            <div class="absolute inset-0 bg-[url('/images/pattern-bg.png')] opacity-5"></div>

            <div class="container relative mx-auto px-6 lg:px-12 text-center">
                 <h1 class="font-heading text-4xl md:text-5xl font-extrabold text-slate-900 tracking-tight text-balance mb-6">
                    Centers of <br class="hidden md:block" />
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-600 to-teal-500">Knowledge & Community</span>
                </h1>
                <p class="text-slate-500 text-lg md:text-xl max-w-2xl mx-auto text-balance">
                    Connect with the mosques, suraus, and educational centers nurturing our community.
                </p>
                
                 <!-- Search Box -->
                 <div class="max-w-xl mx-auto mt-8">
                    <form action="{{ route('institutions.index') }}" method="GET" class="relative group">
                        <input 
                            type="text" 
                            name="search" 
                            value="{{ request('search') }}"
                            placeholder="{{ __('Search institutions by name...') }}" 
                            class="w-full h-14 pl-12 pr-4 rounded-2xl border-2 border-slate-100 bg-white shadow-lg shadow-slate-200/50 font-medium text-slate-900 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 focus:outline-none transition-all placeholder:text-slate-400"
                        >
                        <svg class="absolute left-4 top-1/2 -translate-y-1/2 h-6 w-6 text-slate-400 group-focus-within:text-emerald-500 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                         @if(request('search'))
                            <a href="{{ route('institutions.index') }}" class="absolute right-4 top-1/2 -translate-y-1/2 text-xs font-bold text-red-500 hover:underline">
                                {{ __('Clear') }}
                            </a>
                        @endif
                    </form>
                 </div>
            </div>
        </div>

        <div class="container mx-auto px-6 lg:px-12 mt-12">
            @if($institutions->isEmpty())
                <div class="text-center py-24 rounded-3xl bg-slate-50/50 border border-dashed border-slate-200">
                    <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-white text-slate-300 shadow-sm mb-6">
                        <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                    </div>
                    <h3 class="text-xl font-bold text-slate-900">{{ __('No institutions found') }}</h3>
                    <p class="text-slate-500 mt-2 max-w-md mx-auto">{{ __('We couldn\'t find any institutions matching your search.') }}</p>
                    <button type="button" @click="window.location.href='{{ route('institutions.index') }}'" class="mt-6 font-semibold text-emerald-600 hover:text-emerald-700">
                        {{ __('Clear Search') }} &rarr;
                    </button>
                </div>
            @else
                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                    @foreach($institutions as $institution)
                        @php
                            $coverUrl = $institution->getFirstMediaUrl('cover', 'banner');
                            $cardInstitutionImageUrl = $coverUrl ?: $institution->getFirstMediaUrl('logo');
                        @endphp
                        <a href="{{ route('institutions.show', $institution) }}" wire:navigate class="group relative bg-white rounded-3xl border border-slate-100 shadow-sm hover:shadow-xl hover:shadow-emerald-900/5 hover:-translate-y-1 transition-all duration-300 flex flex-col overflow-hidden">
                            <!-- Banner Area (16:9, cover-first) -->
                            <div class="aspect-video bg-slate-50 relative overflow-hidden">
                                @if($cardInstitutionImageUrl)
                                    <img src="{{ $cardInstitutionImageUrl }}" alt="{{ $institution->name }}" class="h-full w-full object-cover transition-transform duration-700 group-hover:scale-105" loading="lazy">
                                    <div class="absolute inset-0 bg-gradient-to-t from-slate-900/35 via-slate-900/10 to-transparent"></div>
                                @else
                                    <div class="absolute inset-0 bg-gradient-to-br from-emerald-50 to-teal-50 opacity-100 group-hover:opacity-90 transition-opacity"></div>
                                    <svg class="absolute right-0 bottom-0 text-emerald-100/50 w-32 h-32 transform translate-x-8 translate-y-8" fill="currentColor" viewBox="0 0 24 24">
                                         <path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                    </svg>
                                @endif
                            </div>
                            
                            <div class="p-6 pt-6 relative flex-1 flex flex-col">
                                <h3 class="font-heading text-lg font-bold text-slate-900 group-hover:text-emerald-700 transition-colors mb-2 leading-tight">
                                    {{ $institution->name }}
                                </h3>
                                
                                @php
                                    $stateName = $institution->addressModel?->state?->name;
                                @endphp
                                @if($stateName)
                                    <p class="text-sm text-slate-500 flex items-center gap-1.5 mb-4 font-medium">
                                        <svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                        {{ $stateName }}
                                    </p>
                                @endif
                                
                                <div class="mt-auto pt-5 border-t border-slate-50 flex items-center justify-between">
                                    <span class="inline-flex items-center gap-1.5 text-xs font-bold text-slate-600 bg-slate-100 px-2.5 py-1 rounded-lg">
                                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                        {{ $institution->events_count }} {{ __('Events') }}
                                    </span>
                                    
                                     <span class="text-sm font-bold text-emerald-600 group-hover:translate-x-1 transition-transform inline-flex items-center">
                                        {{ __('View Details') }} <svg class="w-4 h-4 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                                     </span>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>

                <div class="mt-16">
                    {{ $institutions->withQueryString()->links() }}
                </div>
            @endif
        </div>
    </div>
