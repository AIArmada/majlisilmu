<?php

use App\Models\Speaker;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    #[Computed]
    public function speakers(): LengthAwarePaginator
    {
        $search = request('search');

        return Speaker::query()
            ->withCount('events')
            ->when($search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('bio', 'like', "%{$search}%");
            })
            ->orderBy('name', 'asc')
            ->paginate(12);
    }
};
?>

@extends('layouts.app')

@section('title', __('Speakers') . ' - ' . config('app.name'))

@section('content')
    @php
        $speakers = $this->speakers;
    @endphp

    <div class="relative min-h-screen pb-32">
        <!-- Hero Section -->
        <div class="relative pt-24 pb-16 bg-white border-b border-slate-100 overflow-hidden">
            <div class="absolute inset-0 bg-emerald-50/50"></div>
            <div class="absolute inset-0 bg-[url('/images/pattern-bg.png')] opacity-5"></div>
            
            <div class="container relative mx-auto px-6 lg:px-12 text-center">
                 <h1 class="font-heading text-4xl md:text-5xl font-extrabold text-slate-900 tracking-tight text-balance mb-6">
                    Voices of <br class="hidden md:block" />
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-600 to-teal-500">Knowledge & Wisdom</span>
                </h1>
                <p class="text-slate-500 text-lg md:text-xl max-w-2xl mx-auto text-balance">
                    Scholars, teachers, and speakers sharing their knowledge with the community.
                </p>

                <!-- Search Box -->
                 <div class="max-w-xl mx-auto mt-8">
                    <form action="{{ route('speakers.index') }}" method="GET" class="relative group">
                        <label for="speaker-search" class="sr-only">{{ __('Search speakers') }}</label>
                        <input type="text" id="speaker-search" name="search" value="{{ request('search') }}"
                            placeholder="{{ __('Search speakers by name...') }}"
                             class="w-full h-14 pl-12 pr-4 rounded-2xl border-2 border-slate-100 bg-white shadow-lg shadow-slate-200/50 font-medium text-slate-900 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 focus:outline-none transition-all placeholder:text-slate-400">
                        <svg class="absolute left-4 top-1/2 -translate-y-1/2 h-6 w-6 text-slate-400 group-focus-within:text-emerald-500 transition-colors" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                         @if(request('search'))
                            <a href="{{ route('speakers.index') }}" class="absolute right-4 top-1/2 -translate-y-1/2 text-xs font-bold text-red-500 hover:underline">
                                {{ __('Clear') }}
                            </a>
                        @endif
                    </form>
                 </div>
            </div>
        </div>

        <div class="container mx-auto px-6 lg:px-12 mt-12">
            @if($speakers->isEmpty())
                <div class="text-center py-24 rounded-3xl bg-slate-50/50 border border-dashed border-slate-200">
                    <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-white text-slate-300 shadow-sm mb-6">
                        <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-slate-900">{{ __('No speakers found') }}</h3>
                    <p class="text-slate-500 mt-2 max-w-md mx-auto">
                        {{ __('We couldn\'t find any speakers matching your search.') }}
                    </p>
                    <button type="button" @click="window.location.href='{{ route('speakers.index') }}'" class="mt-6 font-semibold text-emerald-600 hover:text-emerald-700">
                        {{ __('Clear Search') }} &rarr;
                    </button>
                </div>
            @else
                <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-8">
                    @foreach($speakers as $speaker)
                         @php
                            $avatarUrl = $speaker->avatar_url ?: $speaker->getFirstMediaUrl('avatar');
                            // Ensure URL is valid (basic check) and not a placeholder unless we specifically want to handle that
                             $shouldRenderAvatar = !empty($avatarUrl);
                        @endphp
                        <a href="{{ route('speakers.show', $speaker) }}" wire:navigate
                            class="group relative bg-white rounded-3xl border border-slate-100 shadow-sm hover:shadow-xl hover:shadow-emerald-900/5 hover:-translate-y-1 transition-all duration-300 flex flex-col items-center text-center p-8 overflow-hidden z-10">
                            
                            <!-- Background Decoration -->
                            <div class="absolute inset-x-0 top-0 h-24 bg-gradient-to-b from-slate-50 to-transparent -z-10 opacity-50 transition-opacity group-hover:opacity-100"></div>

                            <div class="h-32 w-32 rounded-full p-1 bg-white border border-slate-100 shadow-lg mb-6 relative group-hover:scale-105 transition-transform duration-500">
                                <div class="w-full h-full rounded-full overflow-hidden bg-slate-100 relative">
                                     @if($shouldRenderAvatar)
                                        <img src="{{ $avatarUrl }}" alt="{{ $speaker->name }}"
                                            class="w-full h-full object-cover">
                                    @else
                                        <div class="w-full h-full bg-emerald-500 flex items-center justify-center">
                                            <span class="font-bold text-4xl text-white font-heading">{{ substr($speaker->name, 0, 1) }}</span>
                                        </div>
                                    @endif
                                </div>
                                <div class="absolute bottom-1 right-1 bg-emerald-500 border-2 border-white rounded-full p-1.5 text-white">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" /></svg>
                                </div>
                            </div>

                            <h3
                                class="font-heading text-lg font-bold text-slate-900 group-hover:text-emerald-700 transition-colors mb-2 leading-tight">
                                {{ $speaker->name }}
                            </h3>

                            @if($speaker->title)
                                <span
                                    class="inline-block bg-slate-50 border border-slate-100 text-slate-500 px-3 py-1 rounded-full text-xs font-semibold mb-5 group-hover:bg-emerald-50 group-hover:text-emerald-600 group-hover:border-emerald-100 transition-colors">{{ $speaker->title }}</span>
                            @endif

                            <div class="flex items-center gap-2 text-xs font-bold text-slate-400 mt-auto uppercase tracking-wider">
                                <span class="flex items-center gap-1.5">
                                    {{ $speaker->events_count }} {{ __('Events') }}
                                </span>
                            </div>
                        </a>
                    @endforeach
                </div>

                <div class="mt-16">
                    {{ $speakers->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
