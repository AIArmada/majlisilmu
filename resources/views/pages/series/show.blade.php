@extends('layouts.app')

@section('title', $series->title . ' - ' . config('app.name'))

@section('content')
    @livewire('series.show', ['series' => $series])
@endsection
