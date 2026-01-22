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

    <div class="bg-slate-50 min-h-screen py-20 pb-32">
        <div class="container mx-auto px-6 lg:px-12">
            <!-- Header -->
            <div class="max-w-2xl mb-12">
                <h1 class="font-heading text-4xl font-bold text-slate-900">{{ __('Speakers') }}</h1>
                <p class="text-slate-500 mt-4 text-lg">
                    {{ __('Scholars and teachers sharing their knowledge.') }}
                </p>

                <!-- Search Box -->
                <form action="{{ route('speakers.index') }}" method="GET" class="mt-8 relative max-w-lg">
                    <label for="speaker-search" class="sr-only">{{ __('Search speakers') }}</label>
                    <input type="text" id="speaker-search" name="search" value="{{ request('search') }}"
                        placeholder="{{ __('Search speakers...') }}"
                        class="w-full h-12 pl-11 pr-4 rounded-xl border border-slate-200 bg-white shadow-sm focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 focus:outline-none transition-all placeholder:text-slate-400">
                    <svg class="absolute left-4 top-1/2 -translate-y-1/2 h-5 w-5 text-slate-400" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </form>
            </div>

            @if($speakers->isEmpty())
                <div class="text-center py-32 rounded-3xl bg-white border border-slate-100 shadow-sm">
                    <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-slate-50 text-slate-300 mb-6">
                        <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-slate-900">{{ __('No speakers found') }}</h3>
                    <p class="text-slate-500 mt-2 max-w-md mx-auto">
                        {{ __('We couldn\'t find any speakers matching your search.') }}</p>
                </div>
            @else
                <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-8">
                    @foreach($speakers as $speaker)
                        @php
                            $avatarUrl = $speaker->avatar_url ?: $speaker->getFirstMediaUrl('avatar');
                            $shouldRenderAvatar = $avatarUrl && ! str_contains($avatarUrl, 'via.placeholder.com');
                        @endphp
                        <a href="{{ route('speakers.show', $speaker) }}" wire:navigate
                            class="group relative bg-white rounded-3xl border border-slate-100 shadow-sm hover:shadow-xl hover:shadow-emerald-500/10 hover:-translate-y-1 transition-all duration-300 flex flex-col items-center text-center p-8 overflow-hidden">
                            <div
                                class="h-32 w-32 rounded-full bg-gradient-to-br from-emerald-50 to-teal-50 flex items-center justify-center relative overflow-hidden mb-6 border-4 border-white shadow-lg ring-1 ring-slate-100">
                                @if($shouldRenderAvatar)
                                    <img src="{{ $avatarUrl }}" alt="{{ $speaker->name }}"
                                        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                                @else
                                    <span class="font-bold text-3xl text-emerald-600">{{ substr($speaker->name, 0, 1) }}</span>
                                @endif
                            </div>

                            <h3
                                class="font-heading text-lg font-bold text-slate-900 group-hover:text-emerald-600 transition-colors mb-2">
                                {{ $speaker->name }}
                            </h3>

                            @if($speaker->title)
                                <span
                                    class="bg-slate-50 text-slate-600 px-3 py-1 rounded-full text-xs font-semibold mb-4">{{ $speaker->title }}</span>
                            @endif

                            <div class="flex items-center gap-2 text-xs font-medium text-slate-400 mt-auto">
                                <span class="flex items-center gap-1">
                                    <svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    {{ $speaker->events_count }} Events
                                </span>
                            </div>
                        </a>
                    @endforeach
                </div>

                <div class="mt-12">
                    {{ $speakers->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection