@extends('layouts.app')

@section('title', $institution->name . ' - ' . config('app.name'))

@section('content')
    @livewire('institutions.show', ['institution' => $institution])
@endsection
