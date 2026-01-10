<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>@yield('title', config('app.name'))</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,700&family=Sora:wght@300;400;500;600;700&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="min-h-screen bg-parchment text-ink antialiased">
        <div class="relative min-h-screen overflow-hidden">
            <div class="pointer-events-none absolute inset-0">
                <div class="absolute -top-40 left-[10%] h-[22rem] w-[22rem] rounded-full bg-amber/35 blur-3xl animate-float"></div>
                <div class="absolute top-24 right-[8%] h-[26rem] w-[26rem] rounded-full bg-olive/20 blur-3xl"></div>
                <div class="absolute bottom-[-6rem] left-[30%] h-[24rem] w-[24rem] rounded-full bg-sea/20 blur-3xl"></div>
            </div>

            <div class="relative">
                @php($supportedLocales = config('app.supported_locales', []))
                @php($currentLocale = app()->getLocale())

                <header class="px-6 sm:px-10 lg:px-16 py-6">
                    <nav class="flex items-center justify-between gap-6">
                        <a href="{{ route('home') }}" class="flex items-center gap-3">
                            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-ink text-parchment font-semibold">MI</span>
                            <div class="flex flex-col">
                                <span class="font-display text-lg leading-tight">Majlis Ilmu</span>
                                <span class="text-xs uppercase tracking-[0.24em] text-ink-soft">{{ __('Community atlas') }}</span>
                            </div>
                        </a>
                        <div class="hidden md:flex items-center gap-6 text-sm">
                            <a href="{{ route('events.index') }}" class="transition hover:text-ink-soft">{{ __('Events') }}</a>
                            <a href="{{ route('institutions.index') }}" class="transition hover:text-ink-soft">{{ __('Institutions') }}</a>
                            <a href="{{ route('speakers.index') }}" class="transition hover:text-ink-soft">{{ __('Speakers') }}</a>
                        </div>
                        <div class="flex items-center gap-3">
                            <details class="relative">
                                <summary class="flex cursor-pointer items-center gap-2 rounded-full border border-ink/15 px-3 py-2 text-xs font-medium uppercase tracking-[0.2em] text-ink-soft transition hover:border-ink/30 hover:bg-ink/5">
                                    {{ __('Language') }}
                                    <span class="text-ink">{{ $supportedLocales[$currentLocale] ?? strtoupper($currentLocale) }}</span>
                                </summary>
                                <div class="absolute right-0 mt-2 w-44 rounded-2xl border border-ink/10 bg-white/95 p-2 shadow-xl shadow-ink/10">
                                    @foreach ($supportedLocales as $locale => $label)
                                        <a href="{{ route('locale.switch', $locale) }}" class="flex items-center justify-between rounded-xl px-3 py-2 text-xs font-medium uppercase tracking-[0.2em] transition {{ $locale === $currentLocale ? 'bg-ink text-parchment' : 'text-ink hover:bg-ink/5' }}" aria-current="{{ $locale === $currentLocale ? 'true' : 'false' }}">
                                            <span>{{ $label }}</span>
                                            @if ($locale === $currentLocale)
                                                <span class="text-parchment">✓</span>
                                            @endif
                                        </a>
                                    @endforeach
                                </div>
                            </details>
                            <a href="{{ route('home') }}#submit" class="hidden sm:inline-flex items-center gap-2 rounded-full border border-ink/15 px-4 py-2 text-sm font-medium transition hover:border-ink/30 hover:bg-ink/5">{{ __('Submit event') }}</a>
                            <a href="{{ route('events.index') }}" class="inline-flex items-center gap-2 rounded-full bg-ink px-4 py-2 text-sm font-medium text-parchment shadow-lg shadow-ink/20 transition hover:translate-y-[-1px]">{{ __('Explore') }}</a>
                        </div>
                    </nav>
                </header>

                <main>
                    @yield('content')
                </main>

                <footer class="px-6 sm:px-10 lg:px-16 py-10">
                    <div class="flex flex-col gap-6 border-t border-ink/10 pt-8 text-sm text-ink-soft">
                        <div class="flex flex-col gap-2">
                            <span class="font-display text-base text-ink">Majlis Ilmu</span>
                            <span>{{ __('Discover classes, lectures, and community gatherings across Malaysia.') }}</span>
                        </div>
                        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                            <span>{{ __('Built for the community. Curated with care.') }}</span>
                            <div class="flex items-center gap-4">
                                <a href="{{ route('events.index') }}" class="transition hover:text-ink">{{ __('Browse events') }}</a>
                                <a href="{{ route('institutions.index') }}" class="transition hover:text-ink">{{ __('Find institutions') }}</a>
                            </div>
                        </div>
                    </div>
                </footer>
            </div>
        </div>

        @livewireScripts
    </body>
</html>
