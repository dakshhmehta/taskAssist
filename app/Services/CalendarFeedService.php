<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Spatie\IcalendarGenerator\Components\Calendar;
use Spatie\IcalendarGenerator\Components\Event;

class CalendarFeedService
{
    protected User $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function build(): Calendar
    {
        $calendar = Calendar::create("{$this->user->name}'s Tasks")
            ->refreshInterval(60);

        $tasks = $this->getTasks();

        foreach ($tasks as $task) {
            $calendar->event(
                Event::create($task->title)
                    ->uniqueIdentifier("task-{$task->id}@taskassist")
                    ->startsAt($task->due_date)
                    ->endsAt($this->resolveEndTime($task))
                    ->description($this->buildDescription($task))
            );
        }

        return $calendar;
    }

    public function get(): string
    {
        return $this->build()->get();
    }

    protected function getTasks()
    {
        return Task::where('assignee_id', $this->user->id)
            ->whereNull('completed_at')
            ->where('auto_schedule', true)
            ->whereNotNull('due_date')
            ->where('due_date', '>=', Carbon::now())
            ->where('due_date', '<=', Carbon::now()->addDays(15))
            ->get();
    }

    protected function resolveEndTime(Task $task): Carbon
    {
        $estimate = $task->estimate ?: 30;

        return $task->due_date->copy()->addMinutes($estimate);
    }

    protected function buildDescription(Task $task): string
    {
        $priority = $this->resolvePriorityLabel($task);
        $estimate = $task->estimate_label;
        $parts = array_filter([$priority, $estimate]);

        return implode(' | ', $parts);
    }

    protected function resolvePriorityLabel(Task $task): ?string
    {
        if ($task->is_important && $task->is_urgent) {
            return 'P1';
        }

        if ($task->is_important && ! $task->is_urgent) {
            return 'P2';
        }

        if (! $task->is_important && $task->is_urgent) {
            return 'P3';
        }

        return 'P4';
    }
}
