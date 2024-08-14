<?php

namespace App\Providers\Filament;

use App\Filament\Resources\TaskResource\Widgets\UserWorkingTaskList;
use App\Filament\Resources\UserResource\Widgets\UserPerformance;
use App\Filament\Resources\UserResource\Widgets\UserStar;
use App\Filament\Widgets\QuoteOfDay;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Rmsramos\Activitylog\ActivitylogPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->profile(isSimple: false)
            ->databaseNotifications()
            ->databaseNotificationsPolling('60s')
            ->colors([
                'primary' => Color::Yellow,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->plugins([
                ActivitylogPlugin::make(),
            ])
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                QuoteOfDay::class,
                UserPerformance::class,
                UserWorkingTaskList::class,
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
            ->topNavigation(true)
            ->authMiddleware([
                Authenticate::class,
            ])
            ->navigationItems([
                \Filament\Navigation\NavigationItem::make('Add Task') // TODO: Implement user policy validation to hide if dont have access to create task
                ->sort(5)
                ->group('Tasks')
                ->url('/admin/tasks/create') // The URL you want the link to go to
                ->icon('heroicon-o-plus'),    // Optionally, add an icon
            ]);
    }
}
