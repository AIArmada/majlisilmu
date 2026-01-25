<?php

use App\Models\Speaker;
use Livewire\Component;

new class extends Component {
    public Speaker $speaker;

    public function mount(Speaker $speaker): void
    {
        $speaker->load([
            'events' => function ($query) {
                $query->where('status', 'approved')
                    ->where('visibility', 'public')
                    ->where('starts_at', '>=', now())
                    ->orderBy('starts_at', 'asc')
                    ->take(5);
            },
            'socialMedia',
        ]);

        $this->speaker = $speaker;
    }

    public function rendering($view)
    {
        $view->title($this->speaker->name . ' - ' . config('app.name'));
    }
};
?>

@php
    $speaker = $this->speaker;
    $avatarUrl = $speaker->avatar_url ?: $speaker->getFirstMediaUrl('avatar');
    $shouldRenderAvatar = $avatarUrl && !str_contains($avatarUrl, 'via.placeholder.com');
    $mainUrl = $speaker->getFirstMediaUrl('main');
    $gallery = $speaker->getMedia('gallery');
    $websiteUrl = $speaker->socialMedia->firstWhere('platform', 'website')?->url;
    $facebookUrl = $speaker->socialMedia->firstWhere('platform', 'facebook')?->url;
    $instagramUrl = $speaker->socialMedia->firstWhere('platform', 'instagram')?->url;
    $youtubeUrl = $speaker->socialMedia->firstWhere('platform', 'youtube')?->url;
@endphp



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
            <!-- Photo -->
            <div
                class="h-32 w-32 md:h-48 md:w-48 rounded-full bg-white border-4 border-white shadow-lg flex-shrink-0 overflow-hidden relative">
                @if($shouldRenderAvatar)
                    <img src="{{ $avatarUrl }}" alt="{{ $speaker->name }}" class="w-full h-full object-cover">
                @else
                    <img src="{{ $speaker->default_avatar_url }}" alt="{{ $speaker->name }}"
                        class="w-full h-full object-cover">
                @endif
            </div>

            <div class="flex-grow pt-4">
                <h1 class="font-heading text-3xl md:text-5xl font-bold text-slate-900 mb-2">
                    {{ $speaker->formatted_name }}
                </h1>

                <div class="flex flex-wrap gap-4 mt-4">
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
                    @if($facebookUrl)
                        <a href="{{ $facebookUrl }}" target="_blank"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-blue-50 text-blue-600 font-medium hover:bg-blue-100 transition-colors">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                <path
                                    d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
                            </svg>
                            {{ __('Facebook') }}
                        </a>
                    @endif
                    @if($instagramUrl)
                        <a href="{{ $instagramUrl }}" target="_blank"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-pink-50 text-pink-600 font-medium hover:bg-pink-100 transition-colors">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 2c5.523 0 10 4.477 10 10s-4.477 10-10 10S2 17.523 2 12 6.477 2 12 2zm0 1.818c-4.518 0-8.182 3.664-8.182 8.182 0 4.518 3.664 8.182 8.182 8.182 4.518 0 8.182-3.664 8.182-8.182 0-4.518-3.664-8.182-8.182-8.182zm0 3.636c2.51 0 4.545 2.036 4.545 4.546 0 2.51-2.036 4.545-4.546 4.545-2.51 0-4.545-2.035-4.545-4.545 0-2.51 2.035-4.546 4.545-4.546zm0 1.819a2.727 2.727 0 100 5.454 2.727 2.727 0 000-5.454zM17.455 6.545a1.091 1.091 0 110 2.182 1.091 1.091 0 010-2.182z" />
                            </svg>
                            {{ __('Instagram') }}
                        </a>
                    @endif
                    @if($youtubeUrl)
                        <a href="{{ $youtubeUrl }}" target="_blank"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-red-50 text-red-600 font-medium hover:bg-red-100 transition-colors">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                <path
                                    d="M19.615 3.184c-3.604-.246-11.631-.245-15.23 0-3.897.266-4.356 2.62-4.385 8.816.029 6.185.484 8.549 4.385 8.816 3.6.245 11.626.246 15.23 0 3.897-.266 4.356-2.62 4.385-8.816-.029-6.185-.484-8.549-4.385-8.816zm-10.615 12.816v-8l8 3.993-8 4.007z" />
                            </svg>
                            {{ __('YouTube') }}
                        </a>
                    @endif
                </div>
            </div>
        </div>

        <div class="grid lg:grid-cols-3 gap-8 mt-8">
            <div class="lg:col-span-2 space-y-8">
                @if($mainUrl)
                    <div class="rounded-3xl overflow-hidden bg-slate-100 shadow-sm border border-slate-100 relative group">
                        <img src="{{ $mainUrl }}" alt="{{ $speaker->name }}"
                            class="w-full h-auto shadow-sm transition-transform duration-1000 group-hover:scale-[1.01]">
                        <div
                            class="absolute inset-0 bg-gradient-to-t from-black/20 via-transparent to-transparent opacity-60 pointer-events-none">
                        </div>
                    </div>
                @endif

                @if($speaker->bio)
                    <div class="bg-white rounded-3xl p-8 shadow-sm border border-slate-100">
                        <h2 class="font-heading text-xl font-bold text-slate-900 mb-4">{{ __('Bio') }}</h2>
                        <div class="prose prose-slate max-w-none">
                            {!! $speaker->bio !!}
                        </div>
                    </div>
                @endif

                <!-- Gallery Section -->
                @if($gallery->count() > 0)
                    <div class="bg-white rounded-3xl p-8 shadow-sm border border-slate-100">
                        <h2 class="font-heading text-xl font-bold text-slate-900 mb-4">{{ __('Gallery') }}</h2>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                            @foreach($gallery as $image)
                                <div
                                    class="group relative aspect-square bg-slate-100 rounded-xl overflow-hidden cursor-zoom-in">
                                    <img src="{{ $image->getUrl() }}" alt="Gallery Image"
                                        class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110">
                                    <div class="absolute inset-0 bg-black/0 group-hover:bg-black/10 transition-colors"></div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Upcoming Events -->
                <div>
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="font-heading text-2xl font-bold text-slate-900">{{ __('Upcoming Engagements') }}</h2>
                    </div>

                    @if($speaker->events->isEmpty())
                        <div class="bg-white rounded-3xl p-8 text-center border border-slate-100">
                            <p class="text-slate-500">{{ __('No upcoming events scheduled at the moment.') }}</p>
                        </div>
                    @else
                        <div class="space-y-4">
                            @foreach($speaker->events as $event)
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
                                                {{ $event->title }}
                                            </h3>
                                            <div class="flex items-center gap-2 text-sm text-slate-500 mt-1">
                                                <span>{{ $event->starts_at?->format('h:i A') }}</span>
                                                <span class="w-1 h-1 rounded-full bg-slate-300"></span>
                                                <span>{{ $event->institution?->name ?? __('Online') }}</span>
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
                @if($speaker->email)
                    <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-100">
                        <h3 class="font-heading text-lg font-bold text-slate-900 mb-4">{{ __('Contact') }}</h3>
                        <div class="space-y-4">
                            <div class="flex items-center gap-3">
                                <svg class="w-5 h-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                                <a href="mailto:{{ $speaker->email }}"
                                    class="text-emerald-600 hover:text-emerald-700 font-medium">{{ $speaker->email }}</a>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>