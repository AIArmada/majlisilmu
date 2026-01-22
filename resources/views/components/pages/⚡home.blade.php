<?php

use Livewire\Component;

new class extends Component
{
};
?>

@extends('layouts.app')

@section('title', 'Majlis Ilmu - Cari Kuliah & Majlis Ilmu di Malaysia')

@push('head')
    <meta name="description"
        content="Platform terbesar untuk mencari kuliah, ceramah, tazkirah, dan majlis ilmu di seluruh Malaysia. Cari yang berdekatan dengan anda.">
@endpush

@section('content')
    <!-- Hero Section with Search -->
    <section class="relative overflow-hidden pt-24 pb-20 lg:pt-32 lg:pb-28">
        <!-- Animated Background -->
        <div class="absolute inset-0 z-0">
            <div class="absolute inset-0 bg-gradient-to-br from-slate-900 via-emerald-950 to-slate-900"></div>
            <!-- Geometric Pattern -->
            <div class="absolute inset-0 opacity-10">
                <svg class="w-full h-full" viewBox="0 0 100 100" preserveAspectRatio="none">
                    <defs>
                        <pattern id="islamic-pattern" width="20" height="20" patternUnits="userSpaceOnUse">
                            <circle cx="10" cy="10" r="1" fill="currentColor" class="text-emerald-400" />
                            <path d="M0 10 L10 0 L20 10 L10 20 Z" fill="none" stroke="currentColor" stroke-width="0.5"
                                class="text-emerald-400" />
                        </pattern>
                    </defs>
                    <rect width="100%" height="100%" fill="url(#islamic-pattern)" />
                </svg>
            </div>
            <!-- Gradient Orbs -->
            <div class="absolute top-0 left-1/4 w-96 h-96 bg-emerald-500/20 rounded-full blur-3xl"></div>
            <div class="absolute bottom-0 right-1/4 w-80 h-80 bg-teal-500/20 rounded-full blur-3xl"></div>
        </div>

        <div class="container mx-auto px-6 lg:px-12 relative z-10">
            <div class="max-w-4xl mx-auto text-center">
                <!-- Badge - Lazy loaded component for tonight count -->
                <livewire:home.tonight-badge defer.bundle />

                <!-- Headline -->
                <h1
                    class="font-heading font-black text-4xl sm:text-5xl lg:text-7xl leading-tight text-white tracking-tight">
                    <span class="block">{{ __('Cari Majlis Ilmu') }}</span>
                    <span
                        class="block bg-gradient-to-r from-emerald-400 via-teal-300 to-emerald-400 bg-clip-text text-transparent">
                        {{ __('Berdekatan Anda') }}
                    </span>
                </h1>

                <p class="mt-6 text-lg sm:text-xl text-slate-300 leading-relaxed max-w-2xl mx-auto">
                    {{ __('Temui kuliah, ceramah, dan majlis ilmu di seluruh Malaysia. Dari masjid ke masjid, dari ustaz ke ustaz.') }}
                </p>

                <!-- Search Box -->
                <div class="mt-10 max-w-2xl mx-auto" x-data="{
                    locating: false,
                    locate() {
                        if (this.locating) {
                            return;
                        }

                        if (! navigator.geolocation) {
                            alert('{{ __("Pelayar anda tidak menyokong geolokasi.") }}');
                            return;
                        }

                        this.locating = true;

                        navigator.geolocation.getCurrentPosition((position) => {
                            this.$refs.lat.value = position.coords.latitude;
                            this.$refs.lng.value = position.coords.longitude;
                            this.$refs.form.submit();
                        }, () => {
                            this.locating = false;
                            alert('{{ __("Tidak dapat mendapatkan lokasi anda. Sila aktifkan perkhidmatan lokasi.") }}');
                        });
                    },
                }">
                    <form action="{{ route('events.index') }}" method="GET" x-ref="form" class="relative">
                        <div class="relative">
                            <label for="hero-search" class="sr-only">{{ __('Search events') }}</label>
                            <svg class="absolute left-5 top-1/2 -translate-y-1/2 h-6 w-6 text-slate-400" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            <input type="text" id="hero-search" name="search"
                                placeholder="{{ __('Cari topik, penceramah, atau masjid...') }}"
                                class="w-full h-16 pl-14 pr-36 rounded-2xl bg-white shadow-2xl shadow-black/20 text-slate-900 text-lg focus:outline-none focus:ring-4 focus:ring-emerald-500/30 transition-all placeholder:text-slate-400">
                            <button type="submit"
                                class="absolute right-2 top-1/2 -translate-y-1/2 h-12 px-6 rounded-xl bg-emerald-600 text-white font-semibold hover:bg-emerald-700 transition-colors">
                                {{ __('Cari') }}
                            </button>
                        </div>
                        <input type="hidden" name="lat" x-ref="lat">
                        <input type="hidden" name="lng" x-ref="lng">
                    </form>

                    <!-- Quick Actions -->
                    <div class="mt-6 flex flex-wrap justify-center gap-3">
                        <button type="button" @click="locate" :disabled="locating"
                            class="inline-flex items-center gap-2 px-5 py-3 rounded-xl bg-white/10 border border-white/20 text-white font-medium backdrop-blur-sm hover:bg-white/20 transition-all">
                            <span class="inline-flex items-center gap-2" x-show="!locating">
                                <svg class="w-5 h-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                {{ __('Berdekatan Saya') }}
                            </span>
                            <span class="inline-flex items-center gap-2" x-show="locating" x-cloak>
                                <svg class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                {{ __('Mencari...') }}
                            </span>
                        </button>
                        <a href="{{ route('events.index', ['date' => 'today']) }}" wire:navigate
                            class="inline-flex items-center gap-2 px-5 py-3 rounded-xl bg-white/10 border border-white/20 text-white font-medium backdrop-blur-sm hover:bg-white/20 transition-all">
                            <svg class="w-5 h-5 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                            </svg>
                            {{ __('Malam Ini') }}
                        </a>
                        <a href="{{ route('events.index', ['date' => 'friday']) }}" wire:navigate
                            class="inline-flex items-center gap-2 px-5 py-3 rounded-xl bg-white/10 border border-white/20 text-white font-medium backdrop-blur-sm hover:bg-white/20 transition-all">
                            <svg class="w-5 h-5 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            {{ __('Jumaat Ini') }}
                        </a>
                    </div>
                </div>

                <!-- Stats - Lazy loaded island -->
                <livewire:home.stats lazy.bundle />
            </div>
        </div>
    </section>

    <!-- Tonight's Events - Lazy loaded component -->
    <livewire:home.tonight-events lazy.bundle />

    <!-- Featured Events - Lazy loaded component -->
    <livewire:home.featured-events lazy.bundle />

    <!-- Browse by Date Quick Filter -->
    <section class="bg-white py-12 border-b border-slate-100">
        <div class="container mx-auto px-6 lg:px-12">
            <livewire:home.date-filter defer.bundle />
        </div>
    </section>

    <!-- Browse by State & Topic - Lazy loaded -->
    <livewire:home.browse-by-location lazy.bundle />

    <!-- Upcoming Events Grid - Lazy loaded -->
    <livewire:home.upcoming-events lazy.bundle />

    <!-- CTA Section -->
    <section class="bg-gradient-to-r from-emerald-600 to-teal-600 py-20">
        <div class="container mx-auto px-6 lg:px-12 text-center">
            <h2 class="font-heading text-3xl lg:text-4xl font-bold text-white mb-4">{{ __('Ada Majlis Ilmu?') }}</h2>
            <p class="text-emerald-100 text-lg max-w-xl mx-auto mb-8">
                {{ __('Kongsikan majlis ilmu anda dengan masyarakat. Mudah dan percuma.') }}
            </p>
            <a href="{{ route('submit-event.create') }}" wire:navigate
                class="inline-flex items-center gap-2 px-8 py-4 rounded-xl bg-white text-emerald-700 font-bold shadow-xl shadow-emerald-900/20 hover:shadow-2xl hover:-translate-y-1 transition-all">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                {{ __('Hantar Majlis') }}
            </a>
        </div>
    </section>

@endsection
