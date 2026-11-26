<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Widgets\ProjectTrackerStats;
use App\Services\ProjectTrackerReport;
use Carbon\Carbon;
use Filament\Pages\Page;

class ProjectTracker extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static string $view = 'filament.pages.project-tracker';

    protected static ?string $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 2;

    public string $month = '';

    public bool $includeOwner = true;

    public function mount(): void
    {
        $this->month = now()->subMonthNoOverflow()->format('Y-m');
    }

    public function getReportProperty(): array
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->month)) {
            $this->month = now()->subMonthNoOverflow()->format('Y-m');
        }

        return (new ProjectTrackerReport)->build(Carbon::parse($this->month.'-01'), $this->includeOwner);
    }

    public function getWidgets(): array
    {
        return [
            ProjectTrackerStats::make(),
        ];
    }

    public function getVisibleWidgets(): array
    {
        return $this->filterVisibleWidgets($this->getWidgets());
    }

    public function getWidgetData(): array
    {
        return [
            'month' => $this->report['month'],
            'includeOwner' => $this->includeOwner,
        ];
    }

    public function getColumns(): int
    {
        return 12;
    }

    public static function canAccess(): bool
    {
        return \Gate::allows('viewProjectTracker', auth()->user());
    }
}
