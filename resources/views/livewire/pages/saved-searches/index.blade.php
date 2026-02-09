@section('title', __('Saved Searches') . ' - ' . config('app.name'))

<div class="bg-slate-50 min-h-screen py-12 pb-32">
    <div class="container mx-auto px-6 lg:px-12">
        <div class="max-w-5xl mx-auto space-y-8">
            <div class="text-center">
                <h1 class="font-heading text-4xl font-bold text-slate-900">{{ __('Saved Searches') }}</h1>
                <p class="text-slate-500 mt-3 text-lg">
                    {{ __('Save your most-used filters and run them with one click.') }}
                </p>
            </div>

            @if (session('status'))
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-semibold text-emerald-700">
                    {{ session('status') }}
                </div>
            @endif

            <section class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm">
                <h2 class="font-heading text-xl font-bold text-slate-900 mb-4">{{ __('Create Saved Search') }}</h2>

                <form wire:submit="save" class="space-y-4">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <label for="saved-search-name" class="text-sm font-semibold text-slate-700">{{ __('Name') }}</label>
                            <input id="saved-search-name" type="text" wire:model.blur="name"
                                placeholder="{{ __('e.g. Kuliah Maghrib KL') }}"
                                class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none">
                            @error('name')
                                <p class="text-sm text-danger-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-2">
                            <label for="saved-search-query" class="text-sm font-semibold text-slate-700">{{ __('Keyword') }}</label>
                            <input id="saved-search-query" type="text" wire:model.blur="query"
                                placeholder="{{ __('Optional keyword search') }}"
                                class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none">
                            @error('query')
                                <p class="text-sm text-danger-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-4">
                        <div class="space-y-2">
                            <label for="saved-search-notify" class="text-sm font-semibold text-slate-700">{{ __('Notify') }}</label>
                            <select id="saved-search-notify" wire:model="notify"
                                class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none">
                                <option value="off">{{ __('Off') }}</option>
                                <option value="instant">{{ __('Instant') }}</option>
                                <option value="daily">{{ __('Daily') }}</option>
                                <option value="weekly">{{ __('Weekly') }}</option>
                            </select>
                            @error('notify')
                                <p class="text-sm text-danger-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-2">
                            <label for="saved-search-radius" class="text-sm font-semibold text-slate-700">{{ __('Radius (km)') }}</label>
                            <input id="saved-search-radius" type="number" min="1" max="500" wire:model.blur="radius_km"
                                class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none">
                            @error('radius_km')
                                <p class="text-sm text-danger-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-2">
                            <label for="saved-search-lat" class="text-sm font-semibold text-slate-700">{{ __('Latitude') }}</label>
                            <input id="saved-search-lat" type="text" wire:model.blur="lat"
                                class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none">
                            @error('lat')
                                <p class="text-sm text-danger-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="space-y-2">
                            <label for="saved-search-lng" class="text-sm font-semibold text-slate-700">{{ __('Longitude') }}</label>
                            <input id="saved-search-lng" type="text" wire:model.blur="lng"
                                class="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none">
                            @error('lng')
                                <p class="text-sm text-danger-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    @if($filters !== [])
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-500 mb-2">{{ __('Captured Filters') }}</p>
                            <div class="flex flex-wrap gap-2">
                                @foreach($filters as $filterKey => $filterValue)
                                    @php
                                        $valueLabel = is_array($filterValue) ? implode(', ', $filterValue) : (string) $filterValue;
                                    @endphp
                                    <span class="inline-flex items-center rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-700">
                                        {{ str($filterKey)->replace('_', ' ')->title() }}: {{ $valueLabel }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="flex justify-end">
                        <button type="submit"
                            class="inline-flex items-center justify-center rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 transition-colors">
                            {{ __('Save Search') }}
                        </button>
                    </div>
                </form>
            </section>

            <section class="space-y-4">
                <h2 class="font-heading text-2xl font-bold text-slate-900">{{ __('Your Saved Searches') }}</h2>

                @if($this->savedSearches->isEmpty())
                    <div class="rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-10 text-center">
                        <p class="text-slate-600 font-medium">{{ __('No saved searches yet.') }}</p>
                        <p class="text-slate-500 text-sm mt-1">{{ __('Use the form above or save active filters from the events page.') }}</p>
                    </div>
                @else
                    <div class="grid gap-4 md:grid-cols-2">
                        @foreach($this->savedSearches as $savedSearch)
                            <article wire:key="saved-search-{{ $savedSearch->id }}"
                                class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <h3 class="font-heading text-lg font-bold text-slate-900">{{ $savedSearch->name }}</h3>
                                        <p class="text-xs text-slate-500 mt-1">
                                            {{ __('Updated') }} {{ $savedSearch->updated_at?->diffForHumans() }}
                                        </p>
                                    </div>
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">
                                        {{ str($savedSearch->notify)->title() }}
                                    </span>
                                </div>

                                @if($savedSearch->query)
                                    <p class="mt-3 text-sm text-slate-600">
                                        <span class="font-semibold text-slate-800">{{ __('Keyword:') }}</span>
                                        {{ $savedSearch->query }}
                                    </p>
                                @endif

                                @if(is_array($savedSearch->filters) && $savedSearch->filters !== [])
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        @foreach($savedSearch->filters as $filterKey => $filterValue)
                                            @php
                                                $filterLabel = is_array($filterValue) ? implode(', ', $filterValue) : (string) $filterValue;
                                            @endphp
                                            <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700">
                                                {{ str($filterKey)->replace('_', ' ')->title() }}: {{ $filterLabel }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif

                                <div class="mt-4 flex items-center gap-3">
                                    <a href="{{ route('events.index', $this->toEventQueryParams($savedSearch)) }}" wire:navigate
                                        class="inline-flex items-center justify-center rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-600 transition-colors">
                                        {{ __('Run Search') }}
                                    </a>
                                    <button type="button" wire:click="delete('{{ $savedSearch->id }}')"
                                        wire:confirm="{{ __('Delete this saved search?') }}"
                                        class="inline-flex items-center justify-center rounded-lg border border-red-200 px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-50 transition-colors">
                                        {{ __('Delete') }}
                                    </button>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    </div>
</div>
