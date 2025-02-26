<?php

namespace App\Filament\Pages;

use App\Filament\Resources\HostingResource\Widgets\SitesHavingSSLIssueWidget;
use App\Filament\Widgets\UpcomingDomainRenewals;
use App\Filament\Widgets\UpcomingHostingRenewals;
use App\Filament\Widgets\WpSitesMissingBackup;
use App\Filament\Widgets\WpSitesOutdatedVersion;
use Filament\Pages\Page;

class SiteHealthDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.site-health-dashboard';

    protected static ?string $navigationGroup = 'Reports'; // Group it logically

    public function getWidgets(): array
    {
        return [
            WpSitesMissingBackup::make(),
            WpSitesOutdatedVersion::make(),
            SitesHavingSSLIssueWidget::make(),
            UpcomingDomainRenewals::make(),
            UpcomingHostingRenewals::make(),
        ];
    }

    public function getVisibleWidgets(): array
    {
        return $this->filterVisibleWidgets($this->getWidgets());
    }

    /**
     * @return int | string | array<string, int | string | null>
     */
    public function getColumns(): int | string | array
    {
        return 9;
    }

    public static function canAccess(): bool
    {
        return \Gate::allows('viewSiteHealthDashboard', auth()->user());
    }
}
