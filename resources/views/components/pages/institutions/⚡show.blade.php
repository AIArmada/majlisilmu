<?php

use App\Models\Institution;
use Livewire\Component;

new class extends Component {
    public Institution $institution;

    public array $backgrounds = [
        'https://images.unsplash.com/photo-1564121211835-e88c852648ab?q=80&w=2070&auto=format&fit=crop', // Floating Mosque
        'https://images.unsplash.com/photo-1557992762-b9e784407b9a?q=80&w=2076&auto=format&fit=crop', // Masjid Nabawi Umbrellas
        'https://images.unsplash.com/photo-1582266254565-cfdf1643c5b5?q=80&w=2072&auto=format&fit=crop', // Alhambra detail
        'https://images.unsplash.com/photo-1584286595398-a59f21d313f9?q=80&w=2074&auto=format&fit=crop', // Blue Mosque interior
    ];

    public function mount(Institution $institution): void
    {
        if ($institution->status !== 'verified' && ! auth()->user()?->hasAnyRole(['super_admin', 'moderator'])) {
            abort(404);
        }

        $this->institution = $institution->load([
            'media',
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
    }

    public function rendering($view)
    {
        $view->title($this->institution->name . ' - ' . config('app.name'));
    }
};
?>

@php
    $institution = $this->institution;
    $randomBackground = collect($this->backgrounds)->random();

    $mainUrl = $institution->getFirstMediaUrl('cover', 'banner');
    $logoUrl = $institution->getFirstMediaUrl('logo', 'thumb');
    $cityName = $institution->addressModel?->city?->name;
    $stateName = $institution->addressModel?->state?->name;
    $gallery = $institution->getMedia('gallery');
@endphp



<div class="relative min-h-screen bg-slate-50">
        <!-- Random Aesthetic Background -->
        <div class="fixed inset-0 z-0 overflow-hidden pointer-events-none">
            <div 
                class="absolute inset-0 bg-cover bg-center"
                style="background-image: url('{{ $randomBackground }}')"
            ></div>
            <div class="absolute inset-0 bg-gradient-to-b from-slate-900/40 via-slate-900/60 to-slate-900/80 backdrop-grayscale-[0.3]"></div>
            <div class="absolute inset-0 bg-[url('/images/pattern-bg.png')] opacity-10 mix-blend-overlay"></div>
        </div>

        <div class="relative z-10 container mx-auto px-4 lg:px-8 py-12 lg:py-20">
            <!-- Glass Card Container -->
            <div class="bg-white/95 backdrop-blur-xl rounded-[2.5rem] shadow-2xl border border-white/50 overflow-hidden relative">
                <!-- Decorative Top Border -->
                <div class="h-2 bg-gradient-to-r from-emerald-600 via-teal-500 to-emerald-600"></div>

                <!-- Main Grid -->
                <div class="grid lg:grid-cols-[350px_1fr] gap-0">
                    
                    <!-- Sidebar (Left) -->
                    <div class="bg-slate-50/50 border-r border-slate-100 p-8 lg:p-10 flex flex-col gap-8 relative">
                        <!-- Floral/Geo Pattern Decor -->
                         <div class="absolute top-0 left-0 w-full h-32 bg-gradient-to-b from-emerald-50/50 to-transparent opacity-50"></div>

                        <!-- Logo & Identity -->
                        <div class="relative text-center">
                            <div class="inline-block relative mb-6">
                                <div class="w-40 h-40 rounded-[2rem] bg-white shadow-xl shadow-slate-200/50 p-2 transform rotate-3 hover:rotate-0 transition-transform duration-500 ease-out border-2 border-slate-100">
                                    <div class="w-full h-full rounded-[1.5rem] overflow-hidden flex items-center justify-center bg-gradient-to-br from-slate-50 to-emerald-50/30 relative group">
                                        @if($logoUrl)
                                            <img src="{{ $logoUrl }}" alt="{{ $institution->name }}" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700" width="160" height="160" loading="eager">
                                        @else
                                            <!-- Decorative Pattern Background -->
                                            <div class="absolute inset-0 opacity-10 bg-[url('/images/pattern-bg.png')] bg-repeat"></div>
                                            
                                            <!-- Decorative Geometric Elements -->
                                            <svg class="absolute w-32 h-32 text-emerald-900/5 animate-[spin_60s_linear_infinite]" viewBox="0 0 24 24" fill="currentColor">
                                                 <path d="M12 2L14.85 9.4L22.5 10.5L16.2 15.3L18.5 22L12 18.1L5.5 22L7.8 15.3L1.5 10.5L9.15 9.4L12 2Z" />
                                            </svg>
                                            
                                            <!-- Initials -->
                                            @php
                                                $initials = collect(explode(' ', $institution->name))
                                                    ->filter(fn($w) => !in_array(strtolower($w), ['al', 'bin', 'b.', 'masjid', 'surau', 'pusat']))
                                                    ->map(fn($word) => substr($word, 0, 1))
                                                    ->take(2)
                                                    ->implode('');
                                                
                                                if (empty($initials)) {
                                                     $initials = substr($institution->name, 0, 1);
                                                }
                                            @endphp
                                            <span class="relative font-heading font-black text-5xl text-emerald-700 drop-shadow-sm select-none tracking-tighter">
                                                {{ $initials }}
                                            </span>
                                            
                                            <!-- Inner Border -->
                                            <div class="absolute inset-2 border border-emerald-900/5 rounded-[1rem]"></div>
                                        @endif
                                    </div>
                                </div>
                                <!-- Verified Badge (Static for now) -->
                                <div class="absolute -bottom-2 -right-2 bg-emerald-500 text-white p-2 rounded-full shadow-lg border-4 border-white">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                </div>
                            </div>

                            <h1 class="font-heading text-2xl lg:text-3xl font-bold text-slate-900 leading-tight mb-2">
                                {{ $institution->name }}
                            </h1>
                            @if($cityName || $stateName)
                                <p class="text-slate-500 font-medium flex items-center justify-center gap-2">
                                    <svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                    {{ $cityName }}{{ $stateName ? ', ' . $stateName : '' }}
                                </p>
                            @endif
                        </div>

                        <!-- Info Cards -->
                        <div class="space-y-4">
                            <!-- Contact Info -->
                            <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm">
                                <h3 class="font-heading text-sm font-bold text-slate-400 uppercase tracking-widest mb-4 flex items-center gap-2">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    {{ __('Contact') }}
                                </h3>
                                <div class="space-y-4 text-sm">
                                    @foreach($institution->contacts->where('is_public', true) ?? [] as $contact)
                                        <div class="flex items-start gap-3 group">
                                            <div class="p-2 rounded-lg bg-emerald-50 text-emerald-600 group-hover:bg-emerald-100 transition-colors">
                                                @if($contact['category'] == 'email')
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                                                @else
                                                     <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg>
                                                @endif
                                            </div>
                                            <div class="flex-1 break-all">
                                                <span class="block text-slate-500 text-xs font-semibold uppercase">{{ $contact['type'] ?? 'Main' }}</span>
                                                <span class="font-medium text-slate-800">{{ $contact['value'] }}</span>
                                            </div>
                                        </div>
                                    @endforeach
                                    
                                    @if($institution->addressModel)
                                        <div class="flex items-start gap-3 group">
                                             <div class="p-2 rounded-lg bg-emerald-50 text-emerald-600 group-hover:bg-emerald-100 transition-colors">
                                                 <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                             </div>
                                            <div class="flex-1">
                                                <span class="block text-slate-500 text-xs font-semibold uppercase">{{ __('Address') }}</span>
                                                <p class="font-medium text-slate-800 leading-snug">
                                                    {{ $institution->addressModel->line1 }}
                                                    {{ $institution->addressModel->line2 ? ', ' . $institution->addressModel->line2 : '' }}
                                                    <br>
                                                    {{ $institution->addressModel->postcode }} {{ $institution->addressModel->city?->name }}
                                                </p>
                                                @if($institution->addressModel->waze_url || $institution->addressModel->lat)
                                                    <div class="flex gap-2 mt-2">
                                                        @if($institution->addressModel->waze_url)
                                                            <a href="{{ $institution->addressModel->waze_url }}" target="_blank" class="text-xs px-3 py-1 bg-cyan-50 text-cyan-600 rounded-lg font-bold hover:bg-cyan-100 transition-colors">Waze</a>
                                                        @endif
                                                        @if($institution->addressModel->lat && $institution->addressModel->lng)
                                                             <a href="https://www.google.com/maps/search/?api=1&query={{ $institution->addressModel->lat }},{{ $institution->addressModel->lng }}" target="_blank" class="text-xs px-3 py-1 bg-blue-50 text-blue-600 rounded-lg font-bold hover:bg-blue-100 transition-colors">Maps</a>
                                                        @endif
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <!-- Social Links -->
                            @if($institution->socialMedia->count() > 0)
                                <div class="flex flex-wrap gap-2 justify-center pt-2">
                                    @foreach($institution->socialMedia as $social)
                                        <a href="{{ $social->url }}" target="_blank" class="p-3 rounded-xl bg-white border border-slate-100 hover:border-emerald-200 hover:bg-emerald-50 text-slate-500 hover:text-emerald-600 transition-all shadow-sm hover:shadow-md hover:-translate-y-0.5" title="{{ $social->platform }}">
                                           @switch($social->platform)
                                                @case('facebook') <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg> @break
                                                @case('instagram') <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg> @break
                                                @case('twitter') <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg> @break
                                                @case('youtube') <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg> @break
                                                @default <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" /></svg>
                                           @endswitch
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Main Content (Right) -->
                    <div class="flex flex-col">
                        <!-- Cover Image (Clear Display) -->
                        <div class="relative bg-slate-100 overflow-hidden group">
                             @if($mainUrl)
                                <img src="{{ $mainUrl }}" alt="{{ __('Cover') }}" class="w-full h-auto shadow-sm transition-transform duration-1000 group-hover:scale-[1.01]" loading="lazy">
                            @else
                                <!-- Fallback Cover Pattern -->
                                <div class="w-full h-64 lg:h-96 bg-emerald-900 flex items-center justify-center relative overflow-hidden">
                                     <div class="absolute inset-0 bg-[url('/images/pattern-bg.png')] opacity-10"></div>
                                     <svg class="w-24 h-24 text-white/10" fill="currentColor" viewBox="0 0 24 24"><path d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                                </div>
                            @endif
                             <div class="absolute inset-0 bg-gradient-to-t from-black/40 via-transparent to-transparent opacity-60 pointer-events-none"></div>
                        </div>

                        <!-- Content Sections -->
                        <div class="p-8 lg:p-12 space-y-12">
                            
                            <!-- About Section -->
                            @if($institution->description)
                            <section>
                                <div class="flex items-center gap-4 mb-6">
                                    <div class="h-px bg-slate-200 flex-1"></div>
                                    <h2 class="font-heading text-2xl font-bold text-slate-800 uppercase tracking-widest text-center">
                                        <span class="text-emerald-600 block text-xs mb-1 font-sans font-bold normal-case tracking-normal">{{ __('About Us') }}</span>
                                        {{ __('Our Institution') }}
                                    </h2>
                                    <div class="h-px bg-slate-200 flex-1"></div>
                                </div>
                                <div class="prose prose-lg prose-slate max-w-none text-slate-600 leading-relaxed text-justify">
                                    {!! $institution->description !!}
                                </div>
                            </section>
                            @endif

                            <!-- Gallery Section -->
                            @if($gallery->count() > 0)
                            <section>
                                <div class="flex items-center justify-between mb-8">
                                    <h2 class="font-heading text-2xl font-bold text-slate-800 flex items-center gap-3">
                                        <span class="w-2 h-8 bg-emerald-500 rounded-full"></span>
                                        {{ __('Gallery') }}
                                    </h2>
                                </div>
                                <div class="grid grid-cols-2 lg:grid-cols-3 gap-4">
                                    @foreach($gallery as $image)
                                        <div class="group relative aspect-square bg-slate-100 rounded-2xl overflow-hidden cursor-zoom-in">
                                            <img src="{{ $image->getAvailableUrl(['gallery_thumb']) }}" alt="{{ __('Gallery Image') }}" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110" loading="lazy">
                                            <div class="absolute inset-0 bg-black/0 group-hover:bg-black/10 transition-colors"></div>
                                        </div>
                                    @endforeach
                                </div>
                            </section>
                            @endif

                             <!-- Events Section -->
                            <section>
                                <div class="flex items-center justify-between mb-8">
                                    <h2 class="font-heading text-2xl font-bold text-slate-800 flex items-center gap-3">
                                        <span class="w-2 h-8 bg-emerald-500 rounded-full"></span>
                                        {{ __('Upcoming Gatherings') }}
                                    </h2>
                                    @if($institution->events->count() > 0)
                                        <a href="{{ route('events.index', ['search' => $institution->name]) }}" wire:navigate class="group flex items-center gap-2 text-sm font-bold text-emerald-600 hover:text-emerald-700 transition-colors">
                                            {{ __('View All') }}
                                            <span class="w-6 h-6 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center group-hover:bg-emerald-600 group-hover:text-white transition-all">
                                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                                            </span>
                                        </a>
                                    @endif
                                </div>

                                @if($institution->events->isEmpty())
                                    <div class="bg-slate-50 border border-dashed border-slate-200 rounded-3xl p-12 text-center">
                                        <div class="w-16 h-16 bg-white rounded-full flex items-center justify-center mx-auto mb-4 shadow-sm text-slate-300">
                                            <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                        </div>
                                        <h3 class="font-bold text-slate-900">{{ __('No upcoming events') }}</h3>
                                        <p class="text-slate-500 text-sm mt-1 mb-4">{{ __('Check back later for new updates from this institution.') }}</p>
                                    </div>
                                @else
                                    <div class="grid md:grid-cols-2 gap-6">
                                        @foreach($institution->events as $event)
                                            <a href="{{ route('events.show', $event) }}" wire:navigate class="group bg-white rounded-2xl border border-slate-100 hover:border-emerald-500/30 hover:shadow-xl hover:shadow-emerald-900/5 transition-all p-5 flex gap-5 items-start">
                                                <!-- Date Badge -->
                                                <div class="flex-shrink-0 w-16 h-16 bg-slate-50 rounded-xl flex flex-col items-center justify-center border border-slate-100 group-hover:border-emerald-200 group-hover:bg-emerald-50 transition-colors">
                                                    <span class="text-[0.65rem] font-bold uppercase tracking-wider text-slate-500 group-hover:text-emerald-600">{{ $event->starts_at?->format('M') }}</span>
                                                    <span class="text-xl font-black text-slate-900 group-hover:text-emerald-700 leading-none">{{ $event->starts_at?->format('d') }}</span>
                                                </div>
                                                
                                                <div class="flex-1 min-w-0 py-0.5">
                                                     <p class="text-xs font-bold text-emerald-600 mb-1 uppercase tracking-wide">
                                                        {{ $event->event_type?->getLabel() ?? 'General' }}
                                                    </p>
                                                    <h3 class="font-bold text-slate-900 leading-tight mb-2 group-hover:text-emerald-700 transition-colors line-clamp-2">
                                                        {{ $event->title }}
                                                    </h3>
                                                    <div class="flex items-center gap-3 text-xs font-medium text-slate-500">
                                                        <span class="flex items-center gap-1">
                                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                            {{ $event->starts_at?->format('h:i A') }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                            </section>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
