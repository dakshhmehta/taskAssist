<?php

namespace App\Console\Commands;

use App\Jobs\ScheduleTasksForUser;
use App\Models\User;
use Illuminate\Console\Command;

class ScheduleTasksCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:tasks-schedule';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prepare the incomplete tasks schedule';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $users = User::all();

        foreach($users as &$user){
            dispatch(new ScheduleTasksForUser($user->id));
        }
    }
}
