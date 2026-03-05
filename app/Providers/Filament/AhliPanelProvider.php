<?php

namespace App\Providers\Filament;

use App\Providers\Filament\Concerns\ResolvesPanelDomain;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AhliPanelProvider extends PanelProvider
{
    use ResolvesPanelDomain;

    public function panel(Panel $panel): Panel
    {
        $ahliDomain = $this->resolvePanelDomain('ahli');

        return $panel
            ->id('ahli')
            ->domain($ahliDomain)
            ->path(filled($ahliDomain) ? '' : 'ahli')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->brandLogo(asset('images/milogo.webp'))
            ->brandLogoHeight('3rem')
            ->login()
            ->colors([
                'primary' => Color::Emerald,
                'gray' => Color::Slate,
            ])
            ->font('Outfit')
            ->maxContentWidth('full')
            ->pages([
                Dashboard::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->topNavigation()
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}

