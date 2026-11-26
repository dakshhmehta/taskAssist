<?php

namespace App\Filament\Pages\Widgets;

use App\Services\ProjectTrackerReport;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\Reactive;

class ProjectTrackerStats extends BaseWidget
{
    #[Reactive]
    public ?string $month = null;

    #[Reactive]
    public bool $includeOwner = true;

    protected static ?string $pollingInterval = null;

    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $report = (new ProjectTrackerReport)->build(
            Carbon::parse(($this->month ?: now()->subMonthNoOverflow()->format('Y-m')).'-01'),
            $this->includeOwner
        );

        return [
            Stat::make('Team hours', number_format($report['totals']['hours'], 1))
                ->icon('heroicon-o-clock'),
            Stat::make('Projects', $report['totals']['projects'])
                ->icon('heroicon-o-rectangle-stack'),
            Stat::make('Tasks', $report['totals']['tasks'])
                ->icon('heroicon-o-clipboard-document-list'),
            Stat::make('Members', $report['totals']['members'])
                ->icon('heroicon-o-users'),
        ];
    }
}
