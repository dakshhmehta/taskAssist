<?php

namespace App\Filament\Widgets;

use App\ResellerClub;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RCBalance extends BaseWidget
{
    protected static ?string $pollingInterval = '1h';

    protected function getStats(): array
    {
        $balance = ResellerClub::getBalance();

        return [
            new Stat('Reseller Balance', $balance),
        ];
    }
}
