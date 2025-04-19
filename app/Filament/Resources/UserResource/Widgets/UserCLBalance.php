<?php

namespace App\Filament\Resources\UserResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class UserCLBalance extends BaseWidget
{
    public $user;

    protected function getStats(): array
    {
        $balance = $this->user->balance('cl');

        return [
            new Stat('Available CL', $balance),
        ];
    }
}
