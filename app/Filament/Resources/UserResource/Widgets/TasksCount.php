<?php

namespace App\Filament\Resources\UserResource\Widgets;

use App\Models\Timesheet;
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
            ->whereDate('completed_at', '>=', $this->filterData['startDate'])
            ->whereDate('completed_at', '<=', $this->filterData['endDate'])
            ->count();

        // $timeWorked = Timesheet::select('user_id', \DB::raw('SUM(TIMESTAMPDIFF(MINUTE, start_at, end_at)) AS time'))
        // ->whereNotNull('start_at')
        // ->whereNotNull('end_at')
        // ->where('end_at', '>=', $this->filterData['startDate'])
        // ->where('end_at', '<=', $this->filterData['endDate'])
        // ->where('user_id', $this->user->id)
        // ->groupBy('user_id')
        // ->first();

        return [
            new Stat('Tasks Assigned', $assignedCount),
            new Stat('Tasks Completed', $completedCount),
            // new Stat('Time Worked', $timeWorked ? Timesheet::toHMS($timeWorked->time) : 0) // Display time worked)
        ];
    }
}
