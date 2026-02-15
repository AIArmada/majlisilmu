<?php

use App\Http\Middleware\SetFilamentTimezone;
use Illuminate\Http\Request;

/**
 * @return array{resolved_timezone: string, status_code: int}
 */
function runTimezoneMiddleware(?string $header = null, ?string $cookie = null, ?string $session = null): array
{
    $request = Request::create('/');
    $sessionStore = app('session')->driver();
    $sessionStore->start();

    if ($session !== null) {
        $sessionStore->put('user_timezone', $session);
    }

    $request->setLaravelSession($sessionStore);

    if ($header !== null) {
        $request->headers->set('X-Timezone', $header);
    }

    if ($cookie !== null) {
        $request->cookies->set('user_timezone', $cookie);
    }

    $response = app(SetFilamentTimezone::class)->handle(
        $request,
        static fn () => response('ok', 200),
    );

    return [
        'resolved_timezone' => (string) $request->session()->get('user_timezone'),
        'status_code' => $response->getStatusCode(),
    ];
}

it('stores timezone from X-Timezone header', function () {
    $result = runTimezoneMiddleware(header: 'Asia/Jakarta');

    expect($result['resolved_timezone'])->toBe('Asia/Jakarta');
    expect($result['status_code'])->toBe(200);
});

it('prioritizes X-Timezone header over cookie and session', function () {
    $result = runTimezoneMiddleware(
        header: 'Asia/Bangkok',
        cookie: 'Asia/Singapore',
        session: 'Asia/Manila',
    );

    expect($result['resolved_timezone'])->toBe('Asia/Bangkok');
});

it('uses cookie timezone when header is missing', function () {
    $result = runTimezoneMiddleware(cookie: 'Asia/Singapore', session: 'Asia/Manila');

    expect($result['resolved_timezone'])->toBe('Asia/Singapore');
});

it('uses existing session timezone when header and cookie are missing', function () {
    $result = runTimezoneMiddleware(session: 'Asia/Manila');

    expect($result['resolved_timezone'])->toBe('Asia/Manila');
});

it('falls back to app timezone when provided values are invalid', function () {
    config()->set('app.timezone', 'UTC');

    $result = runTimezoneMiddleware(
        header: 'Invalid/Header',
        cookie: 'Still/Invalid',
        session: 'Not/A-Real-Timezone',
    );

    expect($result['resolved_timezone'])->toBe('UTC');
});
