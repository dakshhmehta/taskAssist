<?php

namespace App\Console\Commands;

use App\Jobs\ScheduleTasksForUser;
use App\Models\Task;
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

        // Update all the dates to unset if its not yet to be planned.
        $tasks = Task::whereNull('estimate')->get();

        foreach($tasks as &$task){
            $this->info($task->title);
            $task->due_date = null;
            $task->save();
        }

        foreach($users as &$user){
            $this->info('Re-prioritizing tasks for '.$user->name);
            dispatch(new ScheduleTasksForUser($user->id));
        }

        return true;
    }
}
