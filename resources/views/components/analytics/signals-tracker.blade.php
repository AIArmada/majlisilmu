@props(['surface' => 'public'])

@php($signalsTracker = app(\App\Services\Signals\SignalsTracker::class)->trackerConfig($surface))
@php($signalsUser = auth()->user())

@if ($signalsTracker !== null)
    <script
        defer
        src="{{ $signalsTracker['script_url'] }}"
        data-endpoint="{{ $signalsTracker['endpoint'] }}"
        data-identify-endpoint="{{ $signalsTracker['identify_endpoint'] }}"
        data-anonymous-cookie-name="{{ $signalsTracker['anonymous_cookie_name'] }}"
        data-session-cookie-name="{{ $signalsTracker['session_cookie_name'] }}"
        data-write-key="{{ $signalsTracker['write_key'] }}"
        @if ($signalsUser !== null)
            data-external-id="{{ $signalsUser->getAuthIdentifier() }}"
            @if (filled($signalsUser->email))
                data-email="{{ $signalsUser->email }}"
            @endif
        @endif></script>
@endif