@extends('layouts.app')

@section('title', $speaker->name . ' - ' . config('app.name'))

@section('content')
    @livewire('speakers.show', ['speaker' => $speaker])
@endsection
