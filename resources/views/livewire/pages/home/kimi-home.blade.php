<div class="min-h-screen bg-slate-50">
    <!-- Hero Section - Clean & Light Design -->
    <section class="relative overflow-hidden bg-white">
        <!-- Subtle Background Pattern -->
        <div class="absolute inset-0">
            <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-emerald-50 via-white to-white"></div>
            <div class="absolute top-0 right-0 w-1/2 h-full bg-gradient-to-bl from-emerald-100/30 via-transparent to-transparent"></div>
            <div class="absolute bottom-0 left-0 w-96 h-96 bg-gradient-to-tr from-teal-100/20 via-transparent to-transparent rounded-full blur-3xl"></div>
        </div>

        <div class="relative z-10 container mx-auto px-6 lg:px-12 pt-32 pb-20">
            <div class="max-w-4xl mx-auto text-center">
                <!-- Badge -->
                <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-emerald-50 border border-emerald-100 mb-8">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span class="text-sm font-medium text-emerald-700">Platform Majlis Ilmu Terbesar di Malaysia</span>
                </div>

                <!-- Main Heading -->
                <h1 class="font-heading text-5xl sm:text-6xl lg:text-7xl font-bold text-slate-900 mb-6 tracking-tight leading-[1.1]">
                    Temui <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-600 to-teal-600">Majlis Ilmu</span><br>di Sekitar Anda
                </h1>

                <!-- Subheading -->
                <p class="text-xl text-slate-600 max-w-2xl mx-auto mb-10 leading-relaxed">
                    Cari dan hadiri kuliah, ceramah, tazkirah, dan program ilmu di seluruh Malaysia.
                    Dari masjid ke surau, semua dalam satu platform.
                </p>

                <!-- Search Box - Modern Minimal -->
                <div class="max-w-2xl mx-auto mb-8" x-data="{
                    locating: false,
                    locate() {
                        if (this.locating) return;
                        if (!navigator.geolocation) {
                            alert('Pelayar anda tidak menyokong geolokasi.');
                            return;
                        }
                        this.locating = true;
                        navigator.geolocation.getCurrentPosition((position) => {
                            window.location.href = '{{ route('events.index') }}?lat=' + position.coords.latitude + '&lng=' + position.coords.longitude;
                        }, () => {
                            this.locating = false;
                            alert('Tidak dapat mendapatkan lokasi anda.');
                        });
                    }
                }">
                    <form action="{{ route('events.index') }}" method="GET" class="relative">
                        <div class="flex items-center bg-white rounded-2xl shadow-xl shadow-slate-200/50 border border-slate-200 overflow-hidden">
                            <div class="flex-1 flex items-center px-6">
                                <svg class="w-5 h-5 text-slate-400 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                                <input
                                    type="text"
                                    name="search"
                                    placeholder="Cari topik, ustaz, atau lokasi..."
                                    class="w-full py-5 bg-transparent border-none focus:ring-0 text-slate-700 placeholder-slate-400 text-lg"
                                >
                            </div>
                            <button
                                type="button"
                                @click="locate"
                                class="hidden sm:flex items-center justify-center px-4 py-5 border-l border-slate-100 hover:bg-slate-50 transition-colors"
                                title="Cari berdekatan"
                            >
                                <svg x-show="!locating" class="w-5 h-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <svg x-show="locating" x-cloak class="animate-spin w-5 h-5 text-emerald-500" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                            </button>
                            <button
                                type="submit"
                                class="px-8 py-5 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold transition-colors"
                            >
                                Cari
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Quick Filters -->
                <div class="flex flex-wrap justify-center gap-3">
                    <a href="{{ route('events.index', ['date' => 'today']) }}" wire:navigate
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium transition-colors">
                        <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                        Hari Ini
                    </a>
                    <a href="{{ route('events.index', ['date' => 'friday']) }}" wire:navigate
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium transition-colors">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        Jumaat Ini
                    </a>
                    <a href="{{ route('events.index', ['date' => 'weekend']) }}" wire:navigate
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium transition-colors">
                        Hujung Minggu
                    </a>
                    <a href="{{ route('events.index', ['date' => 'this-week']) }}" wire:navigate
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium transition-colors">
                        Minggu Ini
                    </a>
                </div>
            </div>
        </div>

        <!-- Stats Bar -->
        <div class="relative z-10 border-t border-slate-100 bg-white/80 backdrop-blur-sm">
            <div class="container mx-auto px-6 lg:px-12 py-6">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
                    <div class="text-center">
                        <div class="text-3xl font-bold text-slate-900">{{ number_format($stats['events'] ?? 0) }}</div>
                        <div class="text-sm text-slate-500 mt-1">Majlis Ilmu</div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-bold text-slate-900">{{ number_format($stats['institutions'] ?? 0) }}</div>
                        <div class="text-sm text-slate-500 mt-1">Institusi</div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-bold text-slate-900">{{ number_format($stats['speakers'] ?? 0) }}</div>
                        <div class="text-sm text-slate-500 mt-1">Penceramah</div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-bold text-emerald-600">{{ number_format($stats['this_week'] ?? 0) }}</div>
                        <div class="text-sm text-slate-500 mt-1">Minggu Ini</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Categories Section -->
    <section class="py-20 bg-white">
        <div class="container mx-auto px-6 lg:px-12">
            <div class="text-center mb-12">
                <h2 class="text-3xl font-bold text-slate-900 mb-4">Jelajah Mengikut Kategori</h2>
                <p class="text-slate-600">Pilih topik yang anda minati</p>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                @foreach($categories as $category)
                    @php
                        $colorClasses = match($category['color']) {
                            'emerald' => ['bg' => 'bg-emerald-50', 'border' => 'border-emerald-200', 'text' => 'text-emerald-600', 'iconBg' => 'bg-emerald-100'],
                            'blue' => ['bg' => 'bg-blue-50', 'border' => 'border-blue-200', 'text' => 'text-blue-600', 'iconBg' => 'bg-blue-100'],
                            'amber' => ['bg' => 'bg-amber-50', 'border' => 'border-amber-200', 'text' => 'text-amber-600', 'iconBg' => 'bg-amber-100'],
                            'violet' => ['bg' => 'bg-violet-50', 'border' => 'border-violet-200', 'text' => 'text-violet-600', 'iconBg' => 'bg-violet-100'],
                            'rose' => ['bg' => 'bg-rose-50', 'border' => 'border-rose-200', 'text' => 'text-rose-600', 'iconBg' => 'bg-rose-100'],
                            'teal' => ['bg' => 'bg-teal-50', 'border' => 'border-teal-200', 'text' => 'text-teal-600', 'iconBg' => 'bg-teal-100'],
                            default => ['bg' => 'bg-slate-50', 'border' => 'border-slate-200', 'text' => 'text-slate-600', 'iconBg' => 'bg-slate-100'],
                        };
                    @endphp
                    <a href="{{ route('events.index', ['search' => $category['search']]) }}" wire:navigate
                        class="group p-6 rounded-2xl bg-slate-50 hover:{{ $colorClasses['bg'] }} border border-slate-100 hover:{{ $colorClasses['border'] }} text-center transition-all hover:shadow-md">
                        <div class="w-12 h-12 mx-auto mb-4 rounded-xl {{ $colorClasses['iconBg'] }} {{ $colorClasses['text'] }} flex items-center justify-center group-hover:scale-110 transition-transform">
                            @if($category['icon'] === 'book-open')
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                </svg>
                            @elseif($category['icon'] === 'document-text')
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                            @elseif($category['icon'] === 'scale')
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" />
                                </svg>
                            @elseif($category['icon'] === 'star')
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                                </svg>
                            @elseif($category['icon'] === 'users')
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                            @else
                                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                </svg>
                            @endif
                        </div>
                        <h3 class="font-semibold text-slate-900 group-hover:{{ $colorClasses['text'] }} transition-colors">{{ $category['name'] }}</h3>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    <!-- Featured Events Section -->
    @if(count($featuredEvents) > 0)
    <section class="py-20 bg-slate-50">
        <div class="container mx-auto px-6 lg:px-12">
            <div class="flex items-center justify-between mb-10">
                <div>
                    <h2 class="text-3xl font-bold text-slate-900 mb-2">Majlis Akan Datang</h2>
                    <p class="text-slate-600">Jangan lepaskan peluang untuk menambah ilmu</p>
                </div>
                <a href="{{ route('events.index') }}" wire:navigate class="hidden sm:inline-flex items-center gap-2 text-emerald-600 font-semibold hover:text-emerald-700 transition-colors">
                    Lihat Semua
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </a>
            </div>

            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($featuredEvents as $event)
                    <a href="{{ route('events.show', $event) }}" wire:navigate
                        class="group bg-white rounded-2xl overflow-hidden border border-slate-200 hover:border-emerald-200 hover:shadow-xl hover:shadow-emerald-100/50 transition-all">
                        <!-- Event Image -->
                        <div class="relative h-48 bg-gradient-to-br from-emerald-100 to-teal-100 overflow-hidden">
                            @if($event->card_image_url)
                                <img src="{{ $event->card_image_url }}" alt="{{ $event->title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                            @else
                                <div class="absolute inset-0 flex items-center justify-center text-emerald-300">
                                    <svg class="w-16 h-16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </div>
                            @endif
                            <!-- Date Badge -->
                            <div class="absolute top-4 left-4 bg-white rounded-xl px-3 py-2 shadow-lg text-center min-w-[60px]">
                                <div class="text-xs font-bold text-emerald-600 uppercase">{{ $event->starts_at->format('M') }}</div>
                                <div class="text-xl font-bold text-slate-900">{{ $event->starts_at->format('d') }}</div>
                            </div>
                        </div>

                        <!-- Content -->
                        <div class="p-6">
                            <h3 class="font-bold text-lg text-slate-900 mb-2 group-hover:text-emerald-600 transition-colors line-clamp-2">{{ $event->title }}</h3>
                            <div class="flex items-center gap-4 text-sm text-slate-500">
                                <span class="flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    {{ $event->starts_at->format('g:i A') }}
                                </span>
                                @if($event->institution)
                                    <span class="flex items-center gap-1">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                        </svg>
                                        {{ $event->institution->name }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="mt-8 text-center sm:hidden">
                <a href="{{ route('events.index') }}" wire:navigate class="inline-flex items-center gap-2 text-emerald-600 font-semibold hover:text-emerald-700 transition-colors">
                    Lihat Semua Majlis
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </a>
            </div>
        </div>
    </section>
    @endif

    <!-- Browse by Location -->
    <section class="py-20 bg-white">
        <div class="container mx-auto px-6 lg:px-12">
            <div class="text-center mb-12">
                <h2 class="text-3xl font-bold text-slate-900 mb-4">Jelajah Mengikut Negeri</h2>
                <p class="text-slate-600">Cari majlis ilmu di negeri anda</p>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3">
                @php
                    $states = [
                        ['name' => 'Johor', 'code' => '01'],
                        ['name' => 'Kedah', 'code' => '02'],
                        ['name' => 'Kelantan', 'code' => '03'],
                        ['name' => 'Melaka', 'code' => '04'],
                        ['name' => 'Negeri Sembilan', 'code' => '05'],
                        ['name' => 'Pahang', 'code' => '06'],
                        ['name' => 'Perak', 'code' => '08'],
                    ];
                @endphp
                @foreach($states as $state)
                    <a href="{{ route('events.index', ['state' => $state['code']]) }}" wire:navigate
                        class="group px-4 py-3 rounded-xl bg-slate-50 hover:bg-emerald-50 border border-slate-100 hover:border-emerald-200 text-center transition-all">
                        <span class="text-sm font-medium text-slate-700 group-hover:text-emerald-700">{{ $state['name'] }}</span>
                    </a>
                @endforeach
            </div>
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3 mt-3 max-w-4xl mx-auto">
                @php
                    $moreStates = [
                        ['name' => 'Perlis', 'code' => '09'],
                        ['name' => 'Pulau Pinang', 'code' => '07'],
                        ['name' => 'Sabah', 'code' => '12'],
                        ['name' => 'Sarawak', 'code' => '13'],
                        ['name' => 'Selangor', 'code' => '10'],
                        ['name' => 'Terengganu', 'code' => '11'],
                    ];
                @endphp
                @foreach($moreStates as $state)
                    <a href="{{ route('events.index', ['state' => $state['code']]) }}" wire:navigate
                        class="group px-4 py-3 rounded-xl bg-slate-50 hover:bg-emerald-50 border border-slate-100 hover:border-emerald-200 text-center transition-all">
                        <span class="text-sm font-medium text-slate-700 group-hover:text-emerald-700">{{ $state['name'] }}</span>
                    </a>
                @endforeach
            </div>
            <div class="mt-3 text-center">
                <a href="{{ route('events.index', ['state' => '14']) }}" wire:navigate
                    class="inline-block px-6 py-3 rounded-xl bg-slate-50 hover:bg-emerald-50 border border-slate-100 hover:border-emerald-200 text-sm font-medium text-slate-700 hover:text-emerald-700 transition-all">
                    Wilayah Persekutuan
                </a>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-20 bg-slate-900">
        <div class="container mx-auto px-6 lg:px-12">
            <div class="max-w-4xl mx-auto text-center">
                <h2 class="text-4xl font-bold text-white mb-6">Ada Majlis Ilmu?</h2>
                <p class="text-xl text-slate-300 mb-10 max-w-2xl mx-auto">
                    Kongsikan program ilmu anda dengan komuniti. Percuma untuk semua masjid, surau, dan penganjur.
                </p>
                <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                    <a href="{{ route('submit-event.create') }}" wire:navigate
                        class="inline-flex items-center gap-2 px-8 py-4 bg-emerald-500 hover:bg-emerald-400 text-white font-bold rounded-xl transition-colors shadow-lg shadow-emerald-500/25">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Hantar Majlis Sekarang
                    </a>
                    <a href="{{ route('events.index') }}" wire:navigate
                        class="inline-flex items-center gap-2 px-8 py-4 bg-white/10 hover:bg-white/20 text-white font-semibold rounded-xl transition-colors">
                        Lihat Semua Majlis
                    </a>
                </div>
            </div>
        </div>
    </section>
</div>
