<?php

namespace App\Filament\Resources\UserResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TasksCount extends BaseWidget
{
    public $user;
    public $filterData;

    protected function getStats(): array
    {
        $assignedCount = $this->user->tasks()
            ->where('assignee_id', $this->user->id)
            ->whereDate('created_at', '>=', $this->filterData['startDate'])
            ->whereDate('created_at', '<=', $this->filterData['endDate'])
            ->count();

        $completedCount = $this->user->tasks()
            ->where('assignee_id', $this->user->id)
            ->whereDate('completed_at', '>=', $this->filterData['startDate'])
            ->whereDate('completed_at', '<=', $this->filterData['endDate'])
            ->count();

        return [
            new Stat('Tasks Assigned', $assignedCount),
            new Stat('Tasks Completed', $completedCount),
        ];
    }
}
