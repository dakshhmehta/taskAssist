<?php

namespace App\Filament\Resources\UserResource\Widgets;

use App\Models\Task;
use App\Models\Timesheet;
use App\Models\User;
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
        $period = Carbon::now()->startOfWeek();
        $user = User::find($this->userId);

        $averageTasks = Task::select('assignee_id', \DB::raw('COUNT(*) / COUNT(DISTINCT DATE(completed_at)) AS avg_tasks'))
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', $period)
            ->where('assignee_id', $this->userId)
            ->groupBy('assignee_id')
            ->first();

        $totalTasks = Task::whereNotNull('completed_at')
            ->where('completed_at', '>=', $period)
            ->where('assignee_id', $this->userId)
            ->count();

        $averageTimePerTask = Timesheet::select('user_id', \DB::raw('AVG(TIMESTAMPDIFF(MINUTE, start_at, end_at)) AS time'))
            ->whereNotNull('start_at')
            ->whereNotNull('end_at')
            ->where('end_at', '>=', $period)
            ->where('user_id', $this->userId)
            ->groupBy('user_id')
            ->first();

        $totalTimeWorked = $user->timeWorkedThisWeek();

        $widgets = [];

        if ($totalTasks > 0) {
            $widgets[] = Stat::make('Completed Tasks', $totalTasks)
                ->icon('heroicon-o-rectangle-stack')
                ->description('in this week');
        }

        if ($averageTasks) {
            // $widgets[] = Stat::make('Completed Tasks / Day', (int) $averageTasks->avg_tasks)
            //     ->description('in this week');
        }

        if ($totalTimeWorked) {
            $widgets[] = Stat::make('Total Time Worked', Timesheet::toHMS($totalTimeWorked))
                ->icon('heroicon-o-clock')
                ->description('in this week');
        }

        $widgets[] = Stat::make('Performance Rating', $user->performanceThisWeek())
            ->icon('heroicon-o-sparkles')
            ->description('in this week');

        $stars = $user->stars;
        if ($stars > 0) {
            $widgets[] = Stat::make('Stars', $stars)
                ->icon('heroicon-o-star');
        }

        return $widgets;
    }
}
