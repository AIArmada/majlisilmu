@extends('layouts.app')

@section('title', __('Institutions') . ' - ' . config('app.name'))

@section('content')
    @livewire('institutions')
@endsection
