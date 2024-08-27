<?php

namespace App\Console\Commands;

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

        $heading = ['#', 'User', 'Performance', 'Time Worked'];
        $data = [];

        $this->warn('Last Week');

        foreach ($users as $i => $user) {
            $user->_performance = (float) $user->performanceThisWeek(-1);
            $user->_time_worked = $user->timeWorkedThisWeek(-1);
        }

        $users = $users->sortByDesc(function ($user) {
            return [$user['week_performance'], $user['_time_worked']];
        });

        foreach ($users as $i => $user) {
            $data[] = [$i + 1, $user->name, $user->_performance, $user->_time_worked];
        }

        $this->table($heading, $data);

        $this->warn('This Week');

        $data = [];

        foreach ($users as $i => $user) {
            $user->_performance = (float) $user->performanceThisWeek();
            $user->_time_worked = $user->timeWorkedThisWeek();
        }

        $users = $users->sortByDesc(function ($user) {
            return [$user['week_performance'], $user['_time_worked']];
        });

        foreach ($users as $i => $user) {
            $data[] = [$i + 1, $user->name, $user->_performance, $user->_time_worked];
        }

        $this->table($heading, $data);
    }
}
