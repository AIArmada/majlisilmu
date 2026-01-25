<?php

use App\Models\Institution;
use App\Models\Speaker;
use App\Models\State;
use App\Models\Topic;
use App\Models\Venue;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new
    #[Layout('layouts.app')]
    class extends Component
{
    #[Computed]
    public function states(): Collection
    {
        return State::query()->orderBy('name')->get();
    }

    #[Computed]
    public function institutions(): Collection
    {
        return Institution::query()->orderBy('name')->get();
    }

    #[Computed]
    public function speakers(): Collection
    {
        return Speaker::query()->orderBy('name')->get();
    }

    #[Computed]
    public function topics(): Collection
    {
        return Topic::query()->orderBy('name')->get();
    }

    #[Computed]
    public function venues(): Collection
    {
        return Venue::query()->orderBy('name')->get();
    }
};
?>

@section('title', __('Submit Event') . ' - ' . config('app.name'))

    @php
        $states = $this->states;
        $institutions = $this->institutions;
        $speakers = $this->speakers;
        $topics = $this->topics;
        $venues = $this->venues;
    @endphp

    <div class="bg-slate-50 min-h-screen py-20 pb-32">
        <div class="container mx-auto px-6 lg:px-12">
            <div class="max-w-3xl mx-auto">
                <!-- Header -->
                <div class="text-center mb-12">
                    <h1 class="font-heading text-4xl font-bold text-slate-900">{{ __('Submit an Event') }}</h1>
                    <p class="text-slate-500 mt-4 text-lg">
                        {{ __('Share a Majlis Ilmu with the community. Your submission will be reviewed before publishing.') }}
                    </p>
                </div>

                <!-- Form -->
                <form action="{{ route('submit-event.store') }}" method="POST" class="bg-white rounded-3xl p-8 shadow-xl shadow-slate-200/50 border border-slate-100">
                    @csrf

                    @if($errors->any())
                        <div class="mb-8 p-4 rounded-xl bg-red-50 border border-red-100">
                            <h3 class="text-red-800 font-bold mb-2">{{ __('Please fix the following errors:') }}</h3>
                            <ul class="list-disc list-inside text-red-700 text-sm">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <!-- Event Details -->
                    <div class="space-y-6">
                        <h2 class="font-heading text-xl font-bold text-slate-900 pb-4 border-b border-slate-100">{{ __('Event Details') }}</h2>

                        <div>
                            <label for="title" class="block text-sm font-semibold text-slate-700 mb-2">{{ __('Event Title') }} <span class="text-red-500">*</span></label>
                            <input type="text" name="title" id="title" value="{{ old('title') }}" required maxlength="160"
                                class="w-full h-12 px-4 rounded-xl border border-slate-200 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 focus:outline-none transition-all"
                                placeholder="{{ __('e.g., Kuliah Maghrib: Tafsir Surah Al-Kahfi') }}">
                        </div>

                        <div class="grid md:grid-cols-2 gap-6">
                            <div>
                                <label for="starts_at" class="block text-sm font-semibold text-slate-700 mb-2">{{ __('Start Date & Time') }} <span class="text-red-500">*</span></label>
                                <input type="datetime-local" name="starts_at" id="starts_at" value="{{ old('starts_at') }}" required
                                    class="w-full h-12 px-4 rounded-xl border border-slate-200 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 focus:outline-none transition-all">
                            </div>
                            <div>
                                <label for="ends_at" class="block text-sm font-semibold text-slate-700 mb-2">{{ __('End Date & Time') }}</label>
                                <input type="datetime-local" name="ends_at" id="ends_at" value="{{ old('ends_at') }}"
                                    class="w-full h-12 px-4 rounded-xl border border-slate-200 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 focus:outline-none transition-all">
                            </div>
                        </div>

                        <div>
                            <label for="description" class="block text-sm font-semibold text-slate-700 mb-2">{{ __('Description') }}</label>
                            <textarea name="description" id="description" rows="4" maxlength="5000"
                                class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 focus:outline-none transition-all resize-none"
                                placeholder="{{ __('Describe the event, topics to be covered, etc.') }}">{{ old('description') }}</textarea>
                        </div>

                        <div class="grid md:grid-cols-2 gap-6">
                            <div>
                                <label for="event_type" class="block text-sm font-semibold text-slate-700 mb-2">{{ __('Event Type') }}</label>
                                <select name="event_type" id="event_type"
                                    class="w-full h-12 px-4 rounded-xl border border-slate-200 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 focus:outline-none transition-all">
                                    @foreach(\App\Enums\EventType::getGroupedOptions() as $group => $options)
                                        <optgroup label="{{ $group }}">
                                            @foreach($options as $value => $label)
                                                <option value="{{ $value }}" {{ old('event_type') == $value ? 'selected' : '' }}>{{ $label }}</option>
                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="gender_restriction" class="block text-sm font-semibold text-slate-700 mb-2">{{ __('Gender') }}</label>
                                <select name="gender_restriction" id="gender_restriction"
                                    class="w-full h-12 px-4 rounded-xl border border-slate-200 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 focus:outline-none transition-all">
                                    @foreach(\App\Enums\EventGenderRestriction::cases() as $case)
                                        <option value="{{ $case->value }}" {{ old('gender_restriction', 'all') == $case->value ? 'selected' : '' }}>{{ $case->getLabel() }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="grid md:grid-cols-2 gap-6 mt-6">
                            <div>
                                <label for="age_group" class="block text-sm font-semibold text-slate-700 mb-2">{{ __('Age Group') }}</label>
                                <select name="age_group" id="age_group"
                                    class="w-full h-12 px-4 rounded-xl border border-slate-200 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 focus:outline-none transition-all">
                                    @foreach(\App\Enums\EventAgeGroup::cases() as $case)
                                        <option value="{{ $case->value }}" {{ old('age_group', 'all_ages') == $case->value ? 'selected' : '' }}>{{ $case->getLabel() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="flex items-center pt-8">
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="children_allowed" value="1" {{ old('children_allowed', true) ? 'checked' : '' }}
                                        class="sr-only peer">
                                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-emerald-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                                    <span class="ml-3 text-sm font-semibold text-slate-700">{{ __('Children Allowed') }}</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Location -->
                    <div class="space-y-6 mt-10">
                        <h2 class="font-heading text-xl font-bold text-slate-900 pb-4 border-b border-slate-100">{{ __('Location') }}</h2>

                        <div>
                            <label for="institution_id" class="block text-sm font-semibold text-slate-700 mb-2">{{ __('Institution') }}</label>
                            <select name="institution_id" id="institution_id"
                                class="w-full h-12 px-4 rounded-xl border border-slate-200 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 focus:outline-none transition-all">
                                <option value="">{{ __('Select an institution (optional)') }}</option>
                                @foreach($institutions as $institution)
                                    <option value="{{ $institution->id }}" {{ old('institution_id') == $institution->id ? 'selected' : '' }}>{{ $institution->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="venue_id" class="block text-sm font-semibold text-slate-700 mb-2">{{ __('Venue') }}</label>
                            <select name="venue_id" id="venue_id"
                                class="w-full h-12 px-4 rounded-xl border border-slate-200 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 focus:outline-none transition-all">
                                <option value="">{{ __('Select a venue or enter new') }}</option>
                                @foreach($venues as $venue)
                                    <option value="{{ $venue->id }}" {{ old('venue_id') == $venue->id ? 'selected' : '' }}>{{ $venue->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div id="new-venue-fields" class="space-y-6 p-4 rounded-xl bg-slate-50 border border-slate-100">
                            <p class="text-sm text-slate-500 font-medium">{{ __('Or enter a new venue:') }}</p>
                            <div>
                                <label for="venue_name" class="block text-sm font-semibold text-slate-700 mb-2">{{ __('Venue Name') }}</label>
                                <input type="text" name="venue_name" id="venue_name" value="{{ old('venue_name') }}" maxlength="200"
                                    class="w-full h-12 px-4 rounded-xl border border-slate-200 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 focus:outline-none transition-all"
                                    placeholder="{{ __('e.g., Masjid Sultan Salahuddin Abdul Aziz Shah') }}">
                            </div>
                            <div>
                                <label for="address" class="block text-sm font-semibold text-slate-700 mb-2">{{ __('Address') }}</label>
                                <input type="text" name="address" id="address" value="{{ old('address') }}" maxlength="500"
                                    class="w-full h-12 px-4 rounded-xl border border-slate-200 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 focus:outline-none transition-all"
                                    placeholder="{{ __('Full address') }}">
                            </div>
                            <div>
                                <label for="state_id" class="block text-sm font-semibold text-slate-700 mb-2">{{ __('State') }}</label>
                                <select name="state_id" id="state_id"
                                    class="w-full h-12 px-4 rounded-xl border border-slate-200 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 focus:outline-none transition-all">
                                    <option value="">{{ __('Select state') }}</option>
                                    @foreach($states as $state)
                                        <option value="{{ $state->id }}" {{ old('state_id') == $state->id ? 'selected' : '' }}>{{ $state->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Speakers -->
                    <div class="space-y-6 mt-10">
                        <h2 class="font-heading text-xl font-bold text-slate-900 pb-4 border-b border-slate-100">{{ __('Speakers') }}</h2>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">{{ __('Select Speakers') }}</label>
                            <div class="grid md:grid-cols-2 gap-3 max-h-48 overflow-y-auto p-4 rounded-xl bg-slate-50 border border-slate-100">
                                @foreach($speakers as $speaker)
                                    <label class="flex items-center gap-3 cursor-pointer">
                                        <input type="checkbox" name="speaker_ids[]" value="{{ $speaker->id }}"
                                            {{ in_array($speaker->id, old('speaker_ids', [])) ? 'checked' : '' }}
                                            class="w-5 h-5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                                        <span class="text-sm text-slate-700">{{ $speaker->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <!-- Your Details -->
                    <div class="space-y-6 mt-10">
                        <h2 class="font-heading text-xl font-bold text-slate-900 pb-4 border-b border-slate-100">{{ __('Your Details') }}</h2>

                        <div>
                            <label for="submitter_name" class="block text-sm font-semibold text-slate-700 mb-2">{{ __('Your Name') }} <span class="text-red-500">*</span></label>
                            <input type="text" name="submitter_name" id="submitter_name" value="{{ old('submitter_name', auth()->user()?->name) }}" required maxlength="100"
                                class="w-full h-12 px-4 rounded-xl border border-slate-200 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 focus:outline-none transition-all">
                        </div>

                        <div class="grid md:grid-cols-2 gap-6">
                            <div>
                                <label for="submitter_email" class="block text-sm font-semibold text-slate-700 mb-2">{{ __('Email') }}</label>
                                <input type="email" name="submitter_email" id="submitter_email" value="{{ old('submitter_email', auth()->user()?->email) }}" maxlength="255"
                                    class="w-full h-12 px-4 rounded-xl border border-slate-200 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 focus:outline-none transition-all"
                                    placeholder="{{ __('your@email.com') }}">
                            </div>
                            <div>
                                <label for="submitter_phone" class="block text-sm font-semibold text-slate-700 mb-2">{{ __('Phone') }}</label>
                                <input type="tel" name="submitter_phone" id="submitter_phone" value="{{ old('submitter_phone') }}" maxlength="20"
                                    class="w-full h-12 px-4 rounded-xl border border-slate-200 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 focus:outline-none transition-all"
                                    placeholder="{{ __('+60123456789') }}">
                            </div>
                        </div>
                        <p class="text-xs text-slate-500">{{ __('Please provide at least email or phone so we can contact you about your submission.') }}</p>
                    </div>

                    <!-- Submit -->
                    <div class="mt-10 pt-6 border-t border-slate-100">
                        <button type="submit"
                            class="w-full h-14 rounded-xl bg-emerald-600 text-white font-bold text-lg hover:bg-emerald-700 transition-colors shadow-lg shadow-emerald-600/30 hover:shadow-emerald-600/40">
                            {{ __('Submit Event for Review') }}
                        </button>
                        <p class="text-center text-sm text-slate-500 mt-4">
                            {{ __('Your event will be reviewed by our moderators within 24-48 hours.') }}
                        </p>
                    </div>
                </form>
            </div>
        </div>
    </div>