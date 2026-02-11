<?php

test('submit event route is protected by event submission throttle middleware', function () {
    $route = app('router')->getRoutes()->getByName('submit-event.create');

    expect($route)->not->toBeNull()
        ->and($route->gatherMiddleware())->toContain('throttle:event-submission');
});
