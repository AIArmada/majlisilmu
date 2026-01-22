<?php

use App\Models\Institution;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    #[Computed]
    public function institutions(): LengthAwarePaginator
    {
        $search = request('search');

        return Institution::query()
            ->withCount('events')
            ->with(['address.state'])
            ->when($search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            })
            ->orderBy('name', 'asc')
            ->paginate(12);
    }
};
?>

@extends('layouts.app')

@section('title', __('Institutions') . ' - ' . config('app.name'))

@section('content')
    @php
        $institutions = $this->institutions;
    @endphp

    <div class="bg-slate-50 min-h-screen py-20 pb-32">
        <div class="container mx-auto px-6 lg:px-12">
            <!-- Header -->
            <div class="max-w-2xl mb-12">
                <h1 class="font-heading text-4xl font-bold text-slate-900">{{ __('Institutions') }}</h1>
                <p class="text-slate-500 mt-4 text-lg">
                    {{ __('Centers of knowledge and community.') }}
                </p>
                
                <!-- Search Box -->
                <form action="{{ route('institutions.index') }}" method="GET" class="mt-8 relative max-w-lg">
                    <input 
                        type="text" 
                        name="search" 
                        value="{{ request('search') }}"
                        placeholder="{{ __('Search institutions...') }}" 
                        class="w-full h-12 pl-11 pr-4 rounded-xl border border-slate-200 bg-white shadow-sm focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 focus:outline-none transition-all placeholder:text-slate-400"
                    >
                    <svg class="absolute left-4 top-1/2 -translate-y-1/2 h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                </form>
            </div>

            @if($institutions->isEmpty())
                <div class="text-center py-32 rounded-3xl bg-white border border-slate-100 shadow-sm">
                    <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-slate-50 text-slate-300 mb-6">
                        <svg class="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                    </div>
                    <h3 class="text-xl font-bold text-slate-900">{{ __('No institutions found') }}</h3>
                    <p class="text-slate-500 mt-2 max-w-md mx-auto">{{ __('We couldn\'t find any institutions matching your search.') }}</p>
                </div>
            @else
                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                    @foreach($institutions as $institution)
                        @php
                            $logoUrl = $institution->getFirstMediaUrl('logo');
                        @endphp
                        <a href="{{ route('institutions.show', $institution) }}" wire:navigate class="group relative bg-white rounded-3xl border border-slate-100 shadow-sm hover:shadow-xl hover:shadow-emerald-500/10 hover:-translate-y-1 transition-all duration-300 flex flex-col overflow-hidden">
                            <div class="h-32 bg-gradient-to-br from-emerald-50 to-teal-50 flex items-center justify-center relative overflow-hidden">
                                @if($logoUrl)
                                    <img src="{{ $logoUrl }}" alt="{{ $institution->name }}" class="w-full h-full object-cover opacity-50 group-hover:scale-105 transition-transform duration-500">
                                @else
                                    <svg class="w-16 h-16 text-emerald-200 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                                @endif
                            </div>
                            
                            <div class="p-6 relative">
                                <div class="absolute -top-10 left-6 h-20 w-20 rounded-2xl bg-white border-2 border-white shadow-lg flex items-center justify-center overflow-hidden">
                                     @if($logoUrl)
                                        <img src="{{ $logoUrl }}" class="w-full h-full object-cover">
                                    @else
                                        <span class="font-bold text-2xl text-emerald-600">{{ substr($institution->name, 0, 1) }}</span>
                                    @endif
                                </div>
                                
                                <div class="mt-10">
                                    <h3 class="font-heading text-lg font-bold text-slate-900 group-hover:text-emerald-600 transition-colors mb-2">
                                        {{ $institution->name }}
                                    </h3>
                                    @php
                                        $stateName = $institution->addressModel?->state?->name;
                                    @endphp
                                    @if($stateName)
                                        <p class="text-sm text-slate-500 flex items-center gap-1.5 mb-4">
                                            <svg class="w-4 h-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                            {{ $stateName }}
                                        </p>
                                    @endif
                                    
                                     <div class="flex items-center gap-4 text-xs font-medium text-slate-500 pt-4 border-t border-slate-50">
                                        <span class="flex items-center gap-1">
                                            <svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                            {{ $institution->events_count }} Events
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>

                <div class="mt-12">
                    {{ $institutions->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection