<?php

namespace App\Console\Commands;

use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ProcessRecurringTasks extends Command
{
    protected $signature = 'tasks:process-recurring';

    protected $description = 'Create overdue recurring task instances';

    public function handle(): void
    {
        $tasks = Task::where('is_recurring', true)
            ->whereNotNull('recurrence_type')
            ->whereNull('completed_at')
            ->whereNotNull('due_date')
            ->where('due_date', '<', Carbon::now()->subDay())
            ->get();

        $count = 0;

        foreach ($tasks as $task) {
            $newTask = $task->createNextRecurrence();

            if ($newTask) {
                $count++;
                $this->info("Created next recurrence for task #{$task->id}: {$task->title}");
            }
        }

        $this->info("Processed {$count} recurring task(s).");
    }
}
