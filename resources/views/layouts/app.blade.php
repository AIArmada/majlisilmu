<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/favicon-16x16.png') }}">
    <link rel="icon" type="image/x-icon" href="{{ asset('images/favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @stack('head')
</head>

<body
    class="min-h-screen bg-background text-text-main font-sans antialiased selection:bg-emerald-500/30 selection:text-emerald-900">
    <div class="relative min-h-screen overflow-hidden">
        <!-- Background Gradients -->
        <div class="pointer-events-none absolute inset-0 z-0">
            <div class="absolute inset-0 bg-pattern-islamic opacity-[0.08]"></div>
            <div
                class="absolute -top-40 left-[10%] h-[35rem] w-[35rem] rounded-full bg-emerald-500/10 blur-[100px] animate-pulse">
            </div>
            <div
                class="absolute top-[40%] left-[50%] -translate-x-1/2 h-[45rem] w-[45rem] rounded-full bg-gold-500/5 blur-[120px]">
            </div>
            <div class="absolute top-20 right-[5%] h-[30rem] w-[30rem] rounded-full bg-teal-500/10 blur-[100px]"></div>
            <div
                class="absolute bottom-[-10rem] left-[20%] h-[40rem] w-[40rem] rounded-full bg-emerald-600/5 blur-[120px]">
            </div>
        </div>

        <div class="relative z-10 flex flex-col min-h-screen">
            @php
                $supportedLocales = config('app.supported_locales', []);
                $currentLocale = app()->getLocale();
            @endphp

            <!-- Premium Header -->
            <header
                class="sticky top-0 z-50 w-full border-b border-gold-500/10 bg-white/70 backdrop-blur-md transition-all">
                <nav class="container mx-auto flex h-20 items-center justify-between px-6 lg:px-12">
                    <a href="{{ route('home') }}" wire:navigate class="flex items-center gap-3 group">
                        <img src="{{ asset('images/milogo.webp') }}" alt="Majlis Ilmu"
                            class="h-12 w-12 rounded-xl shadow-lg transition-transform group-hover:scale-105">
                        <div class="flex flex-col">
                            <span
                                class="font-heading text-xl font-bold tracking-tight text-slate-900 group-hover:text-emerald-700 transition-colors">Majlis
                                Ilmu</span>
                            <span
                                class="text-[0.65rem] uppercase tracking-[0.2em] text-slate-500 font-semibold">Community
                                Atlas</span>
                        </div>
                    </a>

                    <div class="hidden md:flex items-center gap-8 text-sm font-medium text-slate-600">
                        <a href="{{ route('events.index') }}" wire:navigate
                            class="hover:text-emerald-600 transition-colors relative after:absolute after:bottom-[-4px] after:left-0 after:h-[2px] after:w-0 after:bg-emerald-500 after:transition-all hover:after:w-full">{{ __('Events') }}</a>
                        <a href="{{ route('institutions.index') }}" wire:navigate
                            class="hover:text-emerald-600 transition-colors relative after:absolute after:bottom-[-4px] after:left-0 after:h-[2px] after:w-0 after:bg-emerald-500 after:transition-all hover:after:w-full">{{ __('Institutions') }}</a>
                        <a href="{{ route('speakers.index') }}" wire:navigate
                            class="hover:text-emerald-600 transition-colors relative after:absolute after:bottom-[-4px] after:left-0 after:h-[2px] after:w-0 after:bg-emerald-500 after:transition-all hover:after:w-full">{{ __('Speakers') }}</a>
                    </div>

                    <div class="flex items-center gap-3">
                        <!-- Language Switcher -->
                        <div class="relative group z-50">
                            <button
                                class="flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold uppercase tracking-wider text-slate-600 hover:border-emerald-500 hover:text-emerald-600 transition-all">
                                {{ $supportedLocales[$currentLocale] ?? strtoupper($currentLocale) }}
                                <svg class="h-3 w-3 text-slate-400 group-hover:text-emerald-500" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                            <div
                                class="absolute right-0 top-full mt-2 w-32 origin-top-right scale-95 opacity-0 invisible group-hover:scale-100 group-hover:opacity-100 group-hover:visible transition-all duration-200 rounded-xl border border-slate-100 bg-white p-1.5 shadow-xl shadow-slate-200/50">
                                @foreach ($supportedLocales as $locale => $label)
                                    <a href="{{ route('locale.switch', $locale) }}"
                                        class="flex items-center justify-between rounded-lg px-3 py-2 text-xs font-semibold uppercase tracking-wider {{ $locale === $currentLocale ? 'bg-emerald-50 text-emerald-700' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900' }}">
                                        {{ $label }}
                                    </a>
                                @endforeach
                            </div>
                        </div>

                        <a href="{{ route('submit-event.create') }}" wire:navigate
                            class="hidden sm:inline-flex items-center justify-center rounded-full bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-200 transition-colors">
                            {{ __('Submit') }}
                        </a>

                        @auth
                            <!-- User Menu -->
                            <div class="relative group z-50">
                                <button
                                    class="flex items-center gap-2 rounded-full border border-slate-200 bg-white p-1 pr-3 hover:border-emerald-500 transition-all">
                                    <div
                                        class="h-8 w-8 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-700 font-bold uppercase">
                                        {{ substr(auth()->user()->name, 0, 1) }}
                                    </div>
                                    <span
                                        class="hidden lg:inline text-xs font-semibold text-slate-700 max-w-[80px] truncate">{{ explode(' ', auth()->user()->name)[0] }}</span>
                                    <svg class="h-3 w-3 text-slate-400" fill="none" viewBox="0 0 24 24"
                                        stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                                <div
                                    class="absolute right-0 top-full mt-2 w-48 origin-top-right scale-95 opacity-0 invisible group-hover:scale-100 group-hover:opacity-100 group-hover:visible transition-all duration-200 rounded-xl border border-slate-100 bg-white p-1.5 shadow-xl shadow-slate-200/50">
                                    <div class="px-3 py-2 border-b border-slate-50 mb-1">
                                        <p class="text-xs text-slate-500">{{ __('Signed in as') }}</p>
                                        <p class="text-sm font-bold text-slate-900 truncate">{{ auth()->user()->email }}</p>
                                    </div>
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit"
                                            class="w-full text-left rounded-lg px-3 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors">
                                            {{ __('Log Out') }}
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @else
                            <div class="flex items-center gap-2">
                                <a href="{{ route('login') }}" wire:navigate
                                    class="hidden lg:inline-flex text-sm font-semibold text-slate-600 hover:text-emerald-600 transition-colors px-3">
                                    {{ __('Log In') }}
                                </a>
                                <a href="{{ route('register') }}" wire:navigate
                                    class="inline-flex items-center justify-center rounded-full bg-emerald-600 px-5 py-2 text-sm font-semibold text-white shadow-lg shadow-emerald-500/30 hover:bg-emerald-700 hover:shadow-emerald-500/40 hover:-translate-y-0.5 transition-all duration-300">
                                    {{ __('Sign Up') }}
                                </a>
                            </div>
                        @endauth
                    </div>
                </nav>
            </header>

            <main class="flex-grow">
                @yield('content')
            </main>

            <!-- Modern Footer -->
            <footer class="mt-20 border-t border-slate-200 bg-white/50 backdrop-blur-sm">
                <div class="container mx-auto px-6 py-12 lg:px-12">
                    <div class="grid gap-12 lg:grid-cols-4">
                        <div class="lg:col-span-2 flex flex-col gap-4">
                            <div class="flex items-center gap-3">
                                <img src="{{ asset('images/milogo.webp') }}" alt="Majlis Ilmu"
                                    class="h-10 w-10 rounded-lg">
                                <span class="font-heading text-lg font-bold text-slate-900">Majlis Ilmu</span>
                            </div>
                            <p class="text-slate-500 max-w-sm leading-relaxed">
                                {{ __('Connecting the community through knowledge. Discover classes, lectures, and gatherings across Malaysia.') }}
                            </p>
                        </div>

                        <div>
                            <h3 class="font-heading font-semibold text-slate-900 mb-4">{{ __('Discover') }}</h3>
                            <ul class="space-y-3 text-slate-500">
                                <li><a href="{{ route('events.index') }}" wire:navigate
                                        class="hover:text-emerald-600 transition-colors">{{ __('Upcoming Events') }}</a>
                                </li>
                                <li><a href="{{ route('institutions.index') }}" wire:navigate
                                        class="hover:text-emerald-600 transition-colors">{{ __('Institutions') }}</a>
                                </li>
                                <li><a href="{{ route('speakers.index') }}" wire:navigate
                                        class="hover:text-emerald-600 transition-colors">{{ __('Speakers') }}</a></li>
                            </ul>
                        </div>

                        <div>
                            <h3 class="font-heading font-semibold text-slate-900 mb-4">{{ __('Community') }}</h3>
                            <ul class="space-y-3 text-slate-500">
                                <li><a href="#"
                                        class="hover:text-emerald-600 transition-colors">{{ __('About Us') }}</a></li>
                                <li><a href="{{ route('home') }}#submit" wire:navigate
                                        class="hover:text-emerald-600 transition-colors">{{ __('Submit Event') }}</a>
                                </li>
                                <li><a href="#"
                                        class="hover:text-emerald-600 transition-colors">{{ __('Contact Support') }}</a>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div
                        class="mt-12 flex flex-col gap-6 border-t border-slate-200 pt-8 md:flex-row md:items-center md:justify-between text-sm text-slate-400">
                        <span>&copy; {{ date('Y') }} Majlis Ilmu. {{ __('All rights reserved.') }}</span>
                        <div class="flex gap-6">
                            <a href="#" class="hover:text-emerald-600 transition-colors">{{ __('Privacy') }}</a>
                            <a href="#" class="hover:text-emerald-600 transition-colors">{{ __('Terms') }}</a>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    @livewireScriptConfig
    @stack('scripts')
</body>

</html>