<?php

use Laravel\Octane\Contracts\OperationTerminated;
use Laravel\Octane\Listeners\CollectGarbage;
use Laravel\Octane\Listeners\DisconnectFromDatabases;

it('has octane reset listeners configured for long-lived workers', function () {
    $listeners = config('octane.listeners.'.OperationTerminated::class);

    expect($listeners)
        ->toBeArray()
        ->toContain(DisconnectFromDatabases::class)
        ->toContain(CollectGarbage::class);
});

it('enables permission reset listener in octane mode by default', function () {
    expect(config('permission.register_octane_reset_listener'))->toBeTrue();
});

it('registers octane commands', function () {
    $artisanCommands = collect(app(\Illuminate\Contracts\Console\Kernel::class)->all());

    expect($artisanCommands->keys())
        ->toContain('octane:start')
        ->toContain('octane:stop')
        ->toContain('octane:status');
});
