<?php

namespace App\Filament\Widgets;

use App\ResellerClub;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RCBalance extends BaseWidget
{
    protected int | string | array $columnSpan = 6;
    protected static ?int $sort = 2;

    protected static ?string $pollingInterval = '1h';

    protected function getColumns(): int {
        return 6;
    }

    protected function getStats(): array
    {
        $balance = ResellerClub::getBalance();

        return [
            new Stat('Reseller Balance', $balance),
        ];
    }
}
