<?php

use Livewire\Attributes\Title;

new #[Title('Majlis Ilmu - Cari Kuliah & Majlis Ilmu di Malaysia')]
    class extends \Livewire\Component {
    };
?>

@push('head')
    <meta name="description"
        content="{{ __('Platform terbesar untuk mencari kuliah, ceramah, tazkirah, dan majlis ilmu di seluruh Malaysia. Cari yang berdekatan dengan anda.') }}">
@endpush

<div class="min-h-screen bg-gradient-to-b from-slate-50 to-white">
    <!-- Hero Section - Light Theme -->
    <section class="relative min-h-[60vh] flex items-center overflow-hidden">
        <!-- Subtle Background Pattern -->
        <div class="absolute inset-0 bg-gradient-to-br from-emerald-50/80 via-white to-gold-50/30"></div>
        <div class="absolute inset-0 bg-[linear-gradient(rgba(16,185,129,0.03)_1px,transparent_1px),linear-gradient(90deg,rgba(16,185,129,0.03)_1px,transparent_1px)] bg-[size:48px_48px]"></div>
        
        <!-- Decorative Elements -->
        <div class="absolute top-20 left-10 w-72 h-72 bg-emerald-200/30 rounded-full blur-3xl"></div>
        <div class="absolute bottom-10 right-10 w-96 h-96 bg-gold-200/20 rounded-full blur-3xl"></div>
        
        <div class="container relative z-10 px-6 mx-auto py-20 lg:py-28">
            <div class="max-w-3xl mx-auto text-center">
                <!-- Badge -->
                <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-emerald-100/80 text-emerald-700 text-sm font-medium mb-6">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    {{ __('Platform Majlis Ilmu Terbesar') }}
                </div>
                
                <!-- Main Heading -->
                <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold text-slate-900 mb-6 leading-tight tracking-tight">
                    {{ __('Cari Majlis Ilmu') }}
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-600 to-teal-600">
                        {{ __('Berdekatan Anda') }}
                    </span>
                </h1>
                
                <!-- Subheading -->
                <p class="text-lg sm:text-xl text-slate-600 mb-10 max-w-2xl mx-auto leading-relaxed">
                    {{ __('Ketahui & hadiri majlis ilmu di mana-mana sahaja. Pilih penceramah atau topik kegemaran anda.') }}
                </p>
                
                <!-- Search Interface - Pill Design -->
                <div class="relative max-w-2xl mx-auto" x-data="{
                    locating: false,
                    locate() {
                        if (this.locating) return;
                        if (!navigator.geolocation) {
                            alert('{{ __('Pelayar anda tidak menyokong geolokasi.') }}');
                            return;
                        }
                        this.locating = true;
                        navigator.geolocation.getCurrentPosition((position) => {
                            this.$refs.lat.value = position.coords.latitude;
                            this.$refs.lng.value = position.coords.longitude;
                            this.$refs.form.submit();
                        }, () => {
                            this.locating = false;
                            alert('{{ __('Tidak dapat mendapatkan lokasi anda.') }}');
                        });
                    }
                }">
                    <form action="{{ route('events.index') }}" method="GET" x-ref="form"
                        class="relative flex items-center bg-white rounded-full shadow-xl shadow-slate-200/50 border border-slate-200/80 hover:border-emerald-300 transition-colors focus-within:border-emerald-500 focus-within:ring-4 focus-within:ring-emerald-500/10">
                        
                        <!-- Search Icon -->
                        <div class="pl-6 pr-3 text-slate-400">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        
                        <!-- Input -->
                        <input type="text" name="search" 
                            placeholder="{{ __('Cari topik, ustaz, atau lokasi...') }}"
                            class="flex-1 py-4 pr-4 bg-transparent border-none outline-none text-slate-900 placeholder-slate-400 text-base">
                        
                        <!-- Location Button -->
                        <button type="button" @click="locate" :disabled="locating"
                            class="hidden sm:flex items-center justify-center w-10 h-10 mr-2 rounded-full bg-slate-100 hover:bg-emerald-100 text-slate-400 hover:text-emerald-600 transition-colors">
                            <svg x-show="!locating" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <svg x-show="locating" x-cloak class="animate-spin w-5 h-5 text-emerald-500" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                        </button>
                        
                        <!-- Search Button -->
                        <button type="submit"
                            class="mr-2 px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold rounded-full transition-colors shadow-lg shadow-emerald-600/20">
                            {{ __('Cari') }}
                        </button>
                        
                        <input type="hidden" name="lat" x-ref="lat">
                        <input type="hidden" name="lng" x-ref="lng">
                    </form>
                </div>
                
                <!-- Quick Filter Chips -->
                <div class="flex flex-wrap justify-center gap-2 mt-8">
                    <a href="{{ route('events.index', ['date' => 'today']) }}" wire:navigate
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white border border-slate-200 text-slate-600 text-sm font-medium hover:border-emerald-400 hover:text-emerald-600 hover:shadow-md transition-all">
                        <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span>
                        {{ __('Malam Ini') }}
                    </a>
                    <a href="{{ route('events.index', ['date' => 'friday']) }}" wire:navigate
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white border border-slate-200 text-slate-600 text-sm font-medium hover:border-emerald-400 hover:text-emerald-600 hover:shadow-md transition-all">
                        <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                        {{ __('Jumaat Ini') }}
                    </a>
                    <a href="{{ route('events.index', ['date' => 'this-week']) }}" wire:navigate
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white border border-slate-200 text-slate-600 text-sm font-medium hover:border-emerald-400 hover:text-emerald-600 hover:shadow-md transition-all">
                        {{ __('Minggu Ini') }}
                    </a>
                    <a href="{{ route('events.index', ['date' => 'weekend']) }}" wire:navigate
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white border border-slate-200 text-slate-600 text-sm font-medium hover:border-emerald-400 hover:text-emerald-600 hover:shadow-md transition-all">
                        {{ __('Hujung Minggu') }}
                    </a>
                    <a href="{{ route('events.index', ['format' => 'online']) }}" wire:navigate
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white border border-slate-200 text-slate-600 text-sm font-medium hover:border-emerald-400 hover:text-emerald-600 hover:shadow-md transition-all">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
                        </svg>
                        {{ __('Online') }}
                    </a>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Stats Bar - Inline -->
    <livewire:glm.stats lazy.bundle />
    
    <!-- Quick Access Section -->
    <livewire:glm.quick-access lazy.bundle />
    
    <!-- Featured Events - Bento Grid -->
    <livewire:glm.featured lazy.bundle />
    
    <!-- Upcoming Events Timeline -->
    <livewire:glm.timeline lazy.bundle />
    
    <!-- Browse Section -->
    <livewire:glm.browse lazy.bundle />
    
    <!-- CTA Section -->
    <livewire:glm.cta lazy.bundle />
</div>
