@php
    /** @var list<string> $styles */
    $styles = $styles ?? ['filament/filament'];
    $stylePackages = array_values(array_diff($styles, ['app']));

    /** @var list<string> $scripts */
    $scripts = $scripts ?? [];
    $scriptPackages = array_values(array_diff($scripts, ['app']));
@endphp

@push('head')
    @filamentStyles(['app'])
    @filamentStyles($stylePackages)
@endpush

@if ($scriptPackages !== [])
    @push('scripts')
        @filamentScripts(['app'])
        @filamentScripts($scriptPackages)
    @endpush
@endif
