<?php

namespace App\Jobs;

use App\Models\Holiday;
use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ScheduleTasksForUser implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $userId;

    /**
     * Create a new job instance.
     */
    public function __construct($userId)
    {
        $this->userId = $userId;

        \Log::debug('Scheduling tasks for ' . $userId);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $tasks = Task::orderBy('id', 'ASC')
            ->whereNull('completed_at') // Incomplete
            ->where('auto_schedule', true) // Auto schedule
            ->whereNotNull('estimate') // Has estimate time
            ->where('assignee_id', $this->userId)
            ->get();

        if ($tasks->count() == 0) {
            return;
        }

        $user = User::find($this->userId);

        $p1Tasks = $tasks->filter(function ($task) {
            return $task->is_urgent == true && $task->is_important == true;
        });

        $p2Tasks = $tasks->filter(function ($task) {
            return $task->is_urgent == false && $task->is_important == true;
        });

        $p3Tasks = $tasks->filter(function ($task) {
            return $task->is_urgent == true && $task->is_important == false;
        });

        $p4Tasks = $tasks->filter(function ($task) {
            return $task->is_urgent == false && $task->is_important == false;
        });

        $allTasks = collect([])
            ->merge($p1Tasks)
            ->merge($p2Tasks)
            ->merge($p3Tasks)
            ->merge($p4Tasks);

        $dailyLimit = $user->work_hours * 60;
        $date = now();

        if($date->isWeekend()){
            do {
                $date = $date->addDay();
            } while ($date->isWeekend() || Holiday::isHoliday($date));
        }

        do {
            $task = $allTasks[0];

            \Log::debug($task->estimate . ' - ' . $task->title);

            if ($dailyLimit - $task->estimate >= 0) {
                $task->due_date = $date->format('Y-m-d');
                $task->save();

                $dailyLimit = $dailyLimit - $task->estimate;

                \Log::debug('Time Left = ' . $dailyLimit);

                if ($dailyLimit == 0) {
                    \Log::debug('Switch to next day');

                    $dailyLimit = $user->work_hours * 60;

                    do {
                        $date = $date->addDay();
                    } while ($date->isWeekend());
                }

                $allTasks = $allTasks->splice(1);
            } else {
                \Log::debug('Switch to next day');

                $dailyLimit = $user->work_hours * 60;
                do {
                    $date = $date->addDay();
                } while ($date->isWeekend() || Holiday::isHoliday($date));
            }
        } while ($allTasks->count() > 0);
    }
}
