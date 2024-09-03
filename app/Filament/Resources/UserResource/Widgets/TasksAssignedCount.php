<?php

namespace App\Filament\Resources\UserResource\Widgets;

use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TasksAssignedCount extends BaseWidget
{
    protected $user;

    protected function getStats(): array
    {
        $user = $this->all();
        dd($user);

        $count = $user->tasks()
            ->where('assignee_id', $user->id)
            ->whereMonth('created_at', now()->month)
            ->count();

        return [
            new Stat('Tasks Assigned', $count),
        ];
    }
}
