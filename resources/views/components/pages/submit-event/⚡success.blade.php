<?php

use Livewire\Component;

new class extends Component
{
};
?>

@extends('layouts.app')

@section('title', __('Event Submitted') . ' - ' . config('app.name'))

@section('content')
    <div class="bg-slate-50 min-h-screen py-20 pb-32">
        <div class="container mx-auto px-6 lg:px-12">
            <div class="max-w-2xl mx-auto text-center">
                <!-- Success Icon -->
                <div
                    class="inline-flex items-center justify-center w-24 h-24 rounded-full bg-emerald-100 text-emerald-600 mb-8">
                    <svg class="w-12 h-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>

                <!-- Message -->
                <h1 class="font-heading text-4xl font-bold text-slate-900 mb-4">{{ __('Event Submitted!') }}</h1>

                @if(session('event_title'))
                    <p class="text-xl text-slate-600 mb-4">
                        <strong>"{{ session('event_title') }}"</strong>
                    </p>
                @endif

                <p class="text-slate-500 text-lg mb-8 max-w-md mx-auto">
                    {{ __('Thank you for your submission! Our moderators will review your event within 24-48 hours.') }}
                </p>

                <!-- What's Next -->
                <div class="bg-white rounded-3xl p-8 shadow-sm border border-slate-100 text-left mb-8">
                    <h2 class="font-heading text-lg font-bold text-slate-900 mb-4">{{ __("What happens next?") }}</h2>
                    <ul class="space-y-3">
                        <li class="flex items-start gap-3">
                            <span
                                class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-emerald-100 text-emerald-600 font-bold text-sm flex-shrink-0 mt-0.5">1</span>
                            <span
                                class="text-slate-600">{{ __('Our moderators will review your event details for accuracy.') }}</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <span
                                class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-emerald-100 text-emerald-600 font-bold text-sm flex-shrink-0 mt-0.5">2</span>
                            <span
                                class="text-slate-600">{{ __('If approved, your event will be published and searchable.') }}</span>
                        </li>
                        <li class="flex items-start gap-3">
                            <span
                                class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-emerald-100 text-emerald-600 font-bold text-sm flex-shrink-0 mt-0.5">3</span>
                            <span
                                class="text-slate-600">{{ __("If changes are needed, we'll contact you via the details you provided.") }}</span>
                        </li>
                    </ul>
                </div>

                <!-- Actions -->
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="{{ route('submit-event.create') }}" wire:navigate
                        class="inline-flex h-12 items-center justify-center rounded-xl bg-emerald-600 px-6 font-semibold text-white shadow-lg shadow-emerald-600/30 hover:bg-emerald-700 transition-colors">
                        {{ __('Submit Another Event') }}
                    </a>
                    <a href="{{ route('events.index') }}" wire:navigate
                        class="inline-flex h-12 items-center justify-center rounded-xl bg-white border border-slate-200 px-6 font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                        {{ __('Browse Events') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection