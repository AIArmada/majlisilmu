<?php

use Livewire\Blaze\Config as BlazeConfig;

it('applies safe blaze optimization paths', function (): void {
    /** @var BlazeConfig $config */
    $config = app(BlazeConfig::class);

    $pageMatches = glob(resource_path('views/components/pages/*home.blade.php'));
    $pageComponentPath = is_array($pageMatches) ? ($pageMatches[0] ?? null) : null;

    $homeMatches = glob(resource_path('views/components/home/*stats.blade.php'));
    $homeComponentPath = is_array($homeMatches) ? ($homeMatches[0] ?? null) : null;

    $glmMatches = glob(resource_path('views/components/glm/*stats.blade.php'));
    $glmComponentPath = is_array($glmMatches) ? ($glmMatches[0] ?? null) : null;

    expect($config->shouldCompile(resource_path('views/components/auth-header.blade.php')))->toBeTrue()
        ->and($config->shouldCompile(resource_path('views/components/layouts/auth.blade.php')))->toBeTrue()
        ->and($config->shouldMemoize(resource_path('views/components/app-logo-icon.blade.php')))->toBeTrue()
        ->and($config->shouldCompile(resource_path('views/components/event-json-ld.blade.php')))->toBeFalse()
        ->and($pageComponentPath)->not->toBeNull()
        ->and($homeComponentPath)->not->toBeNull()
        ->and($glmComponentPath)->not->toBeNull()
        ->and($config->shouldCompile((string) $pageComponentPath))->toBeFalse()
        ->and($config->shouldCompile((string) $homeComponentPath))->toBeFalse()
        ->and($config->shouldCompile((string) $glmComponentPath))->toBeFalse();
});

it('renders fortify login page while blaze optimization is active', function (): void {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('/oauth/google/redirect', false);
});
