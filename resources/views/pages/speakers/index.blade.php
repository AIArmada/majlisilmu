@extends('layouts.app')

@section('title', __('Speakers') . ' - ' . config('app.name'))

@section('content')
    @livewire('speakers')
@endsection
