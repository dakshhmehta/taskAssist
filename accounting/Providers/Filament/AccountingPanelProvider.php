<?php

namespace Ri\Accounting\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Ri\Accounting\Filament\Accounting\Pages\GeneralLedger;

class AccountingPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('accounting')
            ->path('accounting')
            ->colors([
                'primary' => Color::Blue,
            ])
            ->discoverResources(in: app_path('../accounting/Filament/Accounting/Resources'), for: 'Ri\\Accounting\\Filament\\Accounting\\Resources')
            ->discoverPages(in: app_path('../accounting/Filament/Accounting/Pages'), for: 'Ri\\Accounting\\Filament\\Accounting\\Pages')
            ->pages([
                Pages\Dashboard::class,
                GeneralLedger::class,
            ])
            ->discoverWidgets(in: app_path('../accounting/Filament/Accounting/Widgets'), for: 'Ri\\Accounting\\Filament\\Accounting\\Widgets')
            ->widgets([
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
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
