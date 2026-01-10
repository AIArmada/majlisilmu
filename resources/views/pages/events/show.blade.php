@extends('layouts.app')

@section('title', $event->title . ' - ' . config('app.name'))

@section('content')
    @livewire('events.show', ['event' => $event])
@endsection
