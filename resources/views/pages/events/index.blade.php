@extends('layouts.app')

@section('title', __('Events') . ' - ' . config('app.name'))

@section('content')
    @livewire('events')
@endsection
