<?php

namespace App\Filament\Widgets;

use App\ResellerClub;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class RCBalance extends BaseWidget
{
    protected int | string | array $columnSpan = 6;

    protected static ?int $sort = 2;

    protected static ?string $pollingInterval = '1h';

    public static function canView(): bool
    {
        return Auth::user()->is_admin;
    }


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
