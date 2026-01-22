<?php

use App\Models\Institution;
use Livewire\Component;

new class extends Component
{
    public Institution $institution;

    public function mount(Institution $institution): void
    {
        $institution->load([
            'address.state',
            'address.city',
            'events' => function ($query) {
                $query->where('status', 'approved')
                    ->where('visibility', 'public')
                    ->where('starts_at', '>=', now())
                    ->orderBy('starts_at', 'asc')
                    ->take(5);
            },
            'socialMedia',
        ]);

        $this->institution = $institution;
    }
};
?>

@php
    $institution = $this->institution;
@endphp

@extends('layouts.app')

@section('title', $institution->name . ' - ' . config('app.name'))

@section('content')
    <div class="bg-slate-50 min-h-screen">
        <!-- Banner -->
        <div class="h-64 lg:h-80 bg-slate-900 relative overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-emerald-900/50 to-slate-900/90 z-10"></div>
            <!-- Pattern -->
            <div class="absolute inset-0 opacity-20"
                style="background-image: url('{{ asset('images/pattern-bg.png') }}'); background-size: 300px;"></div>
        </div>

        <div class="container mx-auto px-6 lg:px-12 relative z-20 -mt-32 pb-20">
            <div
                class="bg-white rounded-3xl p-8 shadow-xl shadow-slate-200/50 border border-slate-100 flex flex-col md:flex-row gap-8 items-start">
                <!-- Logo -->
                @php
                    $logoUrl = $institution->getFirstMediaUrl('logo');
                @endphp
                <div
                    class="h-32 w-32 md:h-40 md:w-40 rounded-2xl bg-white border-4 border-white shadow-lg flex-shrink-0 overflow-hidden relative">
                    @if($logoUrl)
                        <img src="{{ $logoUrl }}" alt="{{ $institution->name }}" class="w-full h-full object-cover">
                    @else
                        <div
                            class="w-full h-full flex items-center justify-center bg-emerald-50 text-emerald-600 font-bold text-4xl">
                            {{ substr($institution->name, 0, 1) }}
                        </div>
                    @endif
                </div>

                <div class="flex-grow pt-2">
                    <h1 class="font-heading text-3xl md:text-4xl font-bold text-slate-900 mb-2">{{ $institution->name }}
                    </h1>
                    @php
                        $cityName = $institution->addressModel?->city?->name;
                        $stateName = $institution->addressModel?->state?->name;
                        $websiteUrl = $institution->socialMedia->firstWhere('platform', 'website')?->url;
                    @endphp
                    @if($cityName || $stateName)
                        <p class="text-slate-500 text-lg flex items-center gap-2 mb-4">
                            <svg class="w-5 h-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            {{ $cityName }}{{ $cityName && $stateName ? ',' : '' }}
                            {{ $stateName ?? '' }}
                        </p>
                    @endif

                    <div class="flex flex-wrap gap-4 mt-6">
                        @if($websiteUrl)
                            <a href="{{ $websiteUrl }}" target="_blank"
                                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-100 text-slate-600 font-medium hover:bg-slate-200 transition-colors">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
                                </svg>
                                {{ __('Website') }}
                            </a>
                        @endif
                        @if($institution->email)
                            <a href="mailto:{{ $institution->email }}"
                                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-100 text-slate-600 font-medium hover:bg-slate-200 transition-colors">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                                {{ __('Email') }}
                            </a>
                        @endif
                    </div>
                </div>
            </div>

            <div class="grid lg:grid-cols-3 gap-8 mt-8">
                <div class="lg:col-span-2 space-y-8">
                    @if($institution->description)
                        <div class="bg-white rounded-3xl p-8 shadow-sm border border-slate-100">
                            <h2 class="font-heading text-xl font-bold text-slate-900 mb-4">{{ __('About') }}</h2>
                            <div class="prose prose-slate max-w-none">
                                {!! $institution->description !!}
                            </div>
                        </div>
                    @endif

                    <!-- Upcoming Events -->
                    <div>
                        <div class="flex items-center justify-between mb-6">
                            <h2 class="font-heading text-2xl font-bold text-slate-900">{{ __('Upcoming Events') }}</h2>
                            @if($institution->events->count() > 0)
                                <a href="{{ route('events.index', ['search' => $institution->name]) }}" wire:navigate
                                    class="text-sm font-semibold text-emerald-600 hover:text-emerald-700">{{ __('View All') }}
                                    &rarr;</a>
                            @endif
                        </div>

                        @if($institution->events->isEmpty())
                            <div class="bg-white rounded-3xl p-8 text-center border border-slate-100">
                                <p class="text-slate-500">{{ __('No upcoming events scheduled at the moment.') }}</p>
                            </div>
                        @else
                            <div class="space-y-4">
                                @foreach($institution->events as $event)
                                    <a href="{{ route('events.show', $event) }}" wire:navigate
                                        class="block bg-white rounded-2xl p-4 border border-slate-100 hover:border-emerald-500/50 hover:shadow-lg hover:shadow-emerald-500/5 transition-all group">
                                        <div class="flex gap-4">
                                            <div
                                                class="h-20 w-20 rounded-xl bg-slate-100 flex flex-col items-center justify-center text-center flex-shrink-0">
                                                <span
                                                    class="text-xs font-bold text-slate-400 uppercase">{{ $event->starts_at?->format('M') }}</span>
                                                <span
                                                    class="text-xl font-black text-slate-900">{{ $event->starts_at?->format('d') }}</span>
                                            </div>
                                            <div>
                                                <h3
                                                    class="font-bold text-slate-900 group-hover:text-emerald-600 transition-colors line-clamp-1">
                                                    {{ $event->title }}</h3>
                                                <div class="flex items-center gap-2 text-sm text-slate-500 mt-1">
                                                    <span>{{ $event->starts_at?->format('h:i A') }}</span>
                                                    <span class="w-1 h-1 rounded-full bg-slate-300"></span>
                                                    <span>{{ $event->category ?? 'General' }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-100">
                        <h3 class="font-heading text-lg font-bold text-slate-900 mb-4">{{ __('Contact Info') }}</h3>
                        <div class="space-y-4">
                            @if($institution->address)
                                <div class="flex items-start gap-3">
                                    <svg class="w-5 h-5 text-slate-400 mt-0.5" fill="none" viewBox="0 0 24 24"
                                        stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <span class="text-slate-600 text-sm">{{ $institution->address }}</span>
                                </div>
                            @endif
                            @if($institution->phone)
                                <div class="flex items-center gap-3">
                                    <svg class="w-5 h-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                    </svg>
                                    <span class="text-slate-600 text-sm">{{ $institution->phone }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection