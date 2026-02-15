<?php

use Livewire\Component;

new class extends Component {
    //
};
?>

<section class="relative py-20 overflow-hidden">
    <!-- Background -->
    <div class="absolute inset-0 bg-gradient-to-br from-emerald-600 via-emerald-700 to-teal-800"></div>
    
    <!-- Decorative Elements -->
    <div class="absolute inset-0 bg-[linear-gradient(rgba(255,255,255,0.05)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.05)_1px,transparent_1px)] bg-[size:48px_48px]"></div>
    <div class="absolute top-0 left-1/4 w-96 h-96 bg-white/5 rounded-full blur-3xl"></div>
    <div class="absolute bottom-0 right-1/4 w-80 h-80 bg-gold-500/10 rounded-full blur-3xl"></div>
    
    <div class="container relative z-10 px-6 mx-auto">
        <div class="max-w-3xl mx-auto text-center">
            <!-- Icon -->
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-white/10 backdrop-blur-sm mb-6">
                <svg class="w-8 h-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
            </div>
            
            <!-- Heading -->
            <h2 class="text-3xl sm:text-4xl font-bold text-white mb-4">
                {{ __('Ada Majlis Ilmu?') }}
            </h2>
            
            <!-- Description -->
            <p class="text-lg text-emerald-100 mb-8 max-w-xl mx-auto">
                {{ __('Kongsikan kebaikan dengan masyarakat. Platform ini percuma untuk semua masjid, surau, dan penganjur majlis ilmu.') }}
            </p>
            
            <!-- CTA Buttons -->
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="{{ route('submit-event.create') }}" wire:navigate
                    class="inline-flex items-center gap-2 px-8 py-4 bg-white text-emerald-700 font-bold rounded-xl shadow-xl hover:shadow-2xl hover:bg-emerald-50 transition-all transform hover:-translate-y-0.5">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    {{ __('Hantar Majlis Sekarang') }}
                </a>
                
                <a href="{{ route('events.index') }}" wire:navigate
                    class="inline-flex items-center gap-2 px-8 py-4 bg-white/10 backdrop-blur-sm text-white font-semibold rounded-xl border border-white/20 hover:bg-white/20 transition-all">
                    {{ __('Lihat Majlis Sedia Ada') }}
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                    </svg>
                </a>
            </div>
            
            <!-- Trust Indicators -->
            <div class="mt-10 flex flex-wrap items-center justify-center gap-6 text-emerald-200 text-sm">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    {{ __('Percuma Selamanya') }}
                </div>
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    {{ __('Proses Pantas') }}
                </div>
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    {{ __('Sokongan Penuh') }}
                </div>
            </div>
        </div>
    </div>
</section>
