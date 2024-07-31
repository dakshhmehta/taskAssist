<?php

namespace App\Filament\Resources\UserResource\Widgets;

use App\Models\Task;
use App\Models\Timesheet;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class UserPerformance extends BaseWidget
{
    protected $userId;

    protected int | string | array $columnSpan = 12;

    public function __construct($userId = null)
    {
        if ($userId == null) {
            $userId = Auth::user()->id;
        }

        $this->userId = $userId;
    }

    protected function getStats(): array
    {
        $thirtyDaysAgo = Carbon::now()->subWeeks(1)->startOfWeek();

        // Retrieve the average tasks completed by each user in the last 30 days
        $averageTasks = Task::select('assignee_id', \DB::raw('COUNT(*) / COUNT(DISTINCT DATE(completed_at)) AS avg_tasks'))
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', $thirtyDaysAgo)
            ->where('assignee_id', $this->userId)
            ->groupBy('assignee_id')
            ->first();

        $totalTasks = Task::select('assignee_id', \DB::raw('COUNT(*) AS count'))
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', $thirtyDaysAgo)
            ->where('assignee_id', $this->userId)
            ->groupBy('assignee_id')
            ->first();

            // Retrieve the average time taken per task by each user in the last 30 days
        $averageTimePerTask = Timesheet::select('user_id', \DB::raw('AVG(TIMESTAMPDIFF(MINUTE, start_at, end_at)) AS avg_time'))
            ->whereNotNull('start_at')
            ->whereNotNull('end_at')
            ->where('end_at', '>=', $thirtyDaysAgo)
            ->where('user_id', $this->userId)
            ->groupBy('user_id')
            ->first();



        return [
            Stat::make('Completed Tasks', $totalTasks->count)
                ->description('in this week'),

            Stat::make('Completed Tasks / Day', (int) $averageTasks->avg_tasks)
                ->description('in this week'),

            Stat::make('Avg Time / Task', Timesheet::toHMS($averageTimePerTask->avg_time))
                ->description('in this week')

        ];
    }
}
