<?php

namespace App\Filament\Pages;

use App\Services\TeamCalendar as TeamCalendarReport;
use Carbon\Carbon;
use Filament\Pages\Page;

class TeamCalendar extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calendar';

    protected static string $view = 'filament.pages.team-calendar';

    protected static ?string $navigationGroup = 'Reports';

    protected static ?string $navigationLabel = 'Team Calendar';

    protected static ?int $navigationSort = 3;

    public string $month = '';

    public function mount(): void
    {
        $this->month = now()->format('Y-m');
    }

    public function getCalendarProperty(): array
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->month)) {
            $this->month = now()->format('Y-m');
        }

        return (new TeamCalendarReport)->build(Carbon::parse($this->month.'-01'));
    }
}
