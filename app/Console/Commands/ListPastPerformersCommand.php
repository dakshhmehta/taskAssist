<?php

namespace App\Console\Commands;

use App\Models\Timesheet;
use App\Models\User;
use Illuminate\Console\Command;

class ListPastPerformersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:past-performers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Lists the star performers for last week and this week';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $users = User::all();

        $heading = ['#', 'User', 'Time Worked', 'Time Base - Performance', 'Task Base - Performance', 'Performance'];
        $data = [];

        $this->warn('Last Week');

        foreach ($users as $i => $user) {
            $user->week_performance = (float) $user->performanceThisWeek(-1);
            $user->week_time_worked = $user->timeWorkedThisWeek(-1);

            $user->time_base_performance = (float) $user->performanceThisWeekTimeBased(-1);
            $user->task_base_performance = (float) $user->performanceThisWeekTaskBased(-1);
        }

        $users = $users->sortByDesc(function ($user) {
            return [$user['week_performance'], $user['week_time_worked']];
        });

        foreach ($users as $i => $user) {
            $data[] = [$i + 1, $user->name, Timesheet::toHMS($user->week_time_worked), $user->time_base_performance, $user->task_base_performance, $user->week_performance];
        }

        $this->table($heading, $data);

        $this->warn('This Week');

        $data = [];

        foreach ($users as $i => $user) {
            $user->week_performance = (float) $user->performanceThisWeek();
            $user->week_time_worked = $user->timeWorkedThisWeek();

            $user->time_base_performance = (float) $user->performanceThisWeekTimeBased();
            $user->task_base_performance = (float) $user->performanceThisWeekTaskBased();
        }

        $users = $users->sortByDesc(function ($user) {
            return [$user['week_performance'], $user['week_time_worked']];
        });

        foreach ($users as $i => $user) {
            $data[] = [$i + 1, $user->name, Timesheet::toHMS($user->week_time_worked), $user->time_base_performance, $user->task_base_performance, $user->week_performance];
        }

        $this->table($heading, $data);
    }
}
