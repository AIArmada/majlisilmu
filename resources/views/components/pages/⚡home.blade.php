<?php

use Livewire\Component;

new class extends Component {
};
?>

@extends('layouts.app')

@section('title', 'Majlis Ilmu - Cari Kuliah & Majlis Ilmu di Malaysia')

@push('head')
    <meta name="description"
        content="Platform terbesar untuk mencari kuliah, ceramah, tazkirah, dan majlis ilmu di seluruh Malaysia. Cari yang berdekatan dengan anda.">
@endpush

@section('content')
    <!-- Hero Section -->
    <section class="relative min-h-[85vh] flex items-center justify-center overflow-hidden pt-32 pb-20">
        <!-- Background Layer -->
        <div class="absolute inset-0 z-0 bg-slate-950">
            <!-- Aurora Gradients -->
            <div
                class="absolute top-[-20%] left-[-10%] w-[70vw] h-[70vw] rounded-full bg-emerald-900/30 blur-[120px] mix-blend-screen animate-float">
            </div>
            <div class="absolute bottom-[-20%] right-[-10%] w-[60vw] h-[60vw] rounded-full bg-teal-900/20 blur-[100px] mix-blend-screen animate-float"
                style="animation-delay: -5s"></div>

            <!-- Pattern Overlay -->
            <div
                class="absolute inset-0 bg-[url('https://grainy-gradients.vercel.app/noise.svg')] opacity-20 brightness-100 contrast-150 mix-blend-overlay">
            </div>

            <!-- Subtle Grid -->
            <div
                class="absolute inset-0 bg-[linear-gradient(rgba(255,255,255,0.03)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.03)_1px,transparent_1px)] bg-[size:64px_64px] [mask-image:radial-gradient(ellipse_60%_60%_at_50%_50%,#000_70%,transparent_100%)]">
            </div>
        </div>

        <div class="container relative z-10 px-6 mx-auto">
            <div class="flex flex-col items-center max-w-5xl mx-auto text-center stagger-children">

                <!-- Badge -->
                <div class="mb-8 transform hover:scale-105 transition-transform duration-300">
                    <livewire:home.tonight-badge defer.bundle />
                </div>

                <!-- Main Heading -->
                <h1
                    class="font-heading font-extrabold text-5xl sm:text-7xl lg:text-8xl tracking-tight text-white mb-8 leading-[0.9]">
                    <span class="block text-transparent bg-clip-text bg-gradient-to-b from-white to-white/60">Cari Majlis
                        Ilmu</span>
                    <span
                        class="block mt-2 text-transparent bg-clip-text bg-gradient-to-r from-emerald-400 via-teal-200 to-emerald-400 animate-gradient-x bg-[length:200%_auto]">
                        Berdekatan Anda
                    </span>
                </h1>

                <!-- Subheading -->
                <p class="max-w-2xl mx-auto mb-12 text-lg sm:text-xl text-slate-300/90 font-light leading-relaxed">
                    {{ __('Temui kuliah, ceramah, dan majlis ilmu di seluruh Malaysia. Dari masjid ke masjid, diimarahkan oleh para asatizah.') }}
                </p>

                <!-- Search Interface -->
                <div class="w-full max-w-3xl relative group" x-data="{
                            locating: false,
                            locate() {
                                if (this.locating) return;
                                if (!navigator.geolocation) {
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
                            }
                        }">
                    <div
                        class="absolute -inset-1 bg-gradient-to-r from-emerald-500 via-teal-500 to-emerald-500 rounded-2xl opacity-50 group-hover:opacity-100 blur transition duration-500 group-hover:duration-200 animate-tilt">
                    </div>
                    <form action="{{ route('events.index') }}" method="GET" x-ref="form"
                        class="relative bg-slate-900/90 rounded-2xl p-2 flex flex-col sm:flex-row items-center gap-2 border border-white/10 shadow-2xl backdrop-blur-xl">

                        <!-- Search Input -->
                        <div
                            class="relative flex-1 w-full h-14 bg-white/5 rounded-xl border border-white/5 focus-within:bg-white/10 focus-within:border-emerald-500/50 transition-all group/input">
                            <div class="absolute inset-y-0 left-4 flex items-center pointer-events-none">
                                <svg class="w-5 h-5 text-slate-400 group-focus-within/input:text-emerald-400 transition-colors"
                                    fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                            <input type="text" name="search" placeholder="{{ __('Cari topik, ustaz, atau lokasi...') }}"
                                class="w-full h-full bg-transparent border-none pl-12 pr-4 text-white placeholder-slate-400 focus:ring-0 focus:outline-none text-base">
                        </div>

                        <!-- Locate Button -->
                        <button type="button" @click="locate" :disabled="locating"
                            class="hidden sm:flex items-center justify-center w-14 h-14 rounded-xl bg-white/5 hover:bg-white/10 border border-white/5 text-slate-400 hover:text-emerald-400 transition-all custom-tooltip"
                            title="{{ __('Cari berdekatan saya') }}">
                            <div x-show="!locating">
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                            </div>
                            <div x-show="locating" x-cloak>
                                <svg class="animate-spin w-5 h-5 text-emerald-500" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                        stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                            </div>
                        </button>

                        <!-- Search Button -->
                        <button type="submit"
                            class="w-full sm:w-auto px-8 h-14 bg-emerald-600 hover:bg-emerald-500 text-white font-semibold rounded-xl transition-all shadow-lg hover:shadow-emerald-500/25 flex items-center justify-center gap-2">
                            <span>{{ __('Cari') }}</span>
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M14 5l7 7m0 0l-7 7m7-7H3" />
                            </svg>
                        </button>

                        <input type="hidden" name="lat" x-ref="lat">
                        <input type="hidden" name="lng" x-ref="lng">
                    </form>
                </div>

                <!-- Quick Links -->
                <div class="mt-8 flex flex-wrap justify-center gap-4 text-sm font-medium text-slate-400">
                    <span class="hidden sm:inline">{{ __('Cadangan:') }}</span>
                    <a href="{{ route('events.index', ['date' => 'today']) }}" wire:navigate
                        class="hover:text-amber-400 transition-colors flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-white/5 hover:bg-white/10 border border-white/5">
                        <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span>
                        {{ __('Malam Ini') }}
                    </a>
                    <a href="{{ route('events.index', ['date' => 'friday']) }}" wire:navigate
                        class="hover:text-emerald-400 transition-colors flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-white/5 hover:bg-white/10 border border-white/5">
                        <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                        {{ __('Jumaat Ini') }}
                    </a>
                    <button type="button" @click="document.querySelector('[x-data]').__x.$data.locate()"
                        class="sm:hidden hover:text-emerald-400 transition-colors flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-white/5 hover:bg-white/10 border border-white/5">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        </svg>
                        {{ __('Berdekatan Saya') }}
                    </button>
                </div>

                <!-- Live Stats (Floating) -->
                <div class="mt-16 sm:mt-24">
                    <livewire:home.stats lazy.bundle />
                </div>
            </div>
        </div>
    </section>

    <!-- Tonight's Events - Lazy loaded component -->
    <livewire:home.tonight-events lazy.bundle />

    <!-- Featured Events - Lazy loaded component -->
    <livewire:home.featured-events lazy.bundle />

    <!-- Browse by Date Quick Filter -->
    <section class="bg-slate-50 pb-20 pt-10">
        <div class="container mx-auto px-6 lg:px-12">
            <div class="p-8 bg-white border shadow-sm rounded-3xl border-slate-100">
                <livewire:home.date-filter defer.bundle />
            </div>
        </div>
    </section>

    <!-- Browse by State & Topic - Lazy loaded -->
    <livewire:home.browse-by-location lazy.bundle />

    <!-- Upcoming Events Grid - Lazy loaded -->
    <livewire:home.upcoming-events lazy.bundle />

    <!-- CTA Section -->
    <section class="relative py-24 overflow-hidden bg-slate-950">
        <!-- Background Effects -->
        <div
            class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[800px] h-[800px] bg-emerald-900/20 rounded-full blur-[120px]">
        </div>
        <div
            class="absolute inset-0 bg-[url('https://grainy-gradients.vercel.app/noise.svg')] opacity-20 mix-blend-overlay">
        </div>

        <div class="container relative z-10 px-6 mx-auto">
            <div
                class="max-w-4xl mx-auto overflow-hidden text-center border shadow-2xl bg-white/5 backdrop-blur-2xl rounded-3xl border-white/10 ring-1 ring-white/10">
                <div class="px-8 py-16 sm:px-16 sm:py-20">
                    <h2 class="mb-6 text-3xl font-extrabold tracking-tight text-white font-heading sm:text-4xl lg:text-5xl">
                        {{ __('Ada Majlis Ilmu?') }}
                    </h2>
                    <p class="max-w-2xl mx-auto mb-10 text-lg leading-relaxed text-slate-300">
                        {{ __('Kongsikan kebaikan dengan masyarakat. Platform ini percuma untuk semua masjid, surau, dan penganjur majlis ilmu.') }}
                    </p>

                    <div class="flex flex-col items-center justify-center gap-4 sm:flex-row">
                        <a href="{{ route('submit-event.create') }}" wire:navigate
                            class="inline-flex items-center gap-2 px-8 py-4 font-bold text-white transition-all transform rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 shadow-xl shadow-emerald-900/40 hover:shadow-emerald-500/25 hover:-translate-y-1 group">
                            <svg class="w-5 h-5 transition-transform group-hover:rotate-12" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            {{ __('Hantar Majlis Sekaramg') }}
                        </a>
                        <!-- Optional Secondary Button (e.g. Learn More) -->
                        <a href="#"
                            class="inline-flex items-center gap-2 px-8 py-4 font-medium transition-all rounded-xl text-slate-300 hover:text-white hover:bg-white/5">
                            {{ __('Ketahui Lanjut') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

@endsection