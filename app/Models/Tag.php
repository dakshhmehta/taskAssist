<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use Spatie\Tags\Tag as BaseTag;

class Tag extends BaseTag
{
    public function tasks()
    {
        return $this->morphedByMany(Task::class, 'taggable');
    }

    public function getIncompleteTasksCountAttribute()
    {
        return $this->tasks()->whereNull('completed_at')->count();
    }

    public function getDueDateAttribute()
    {
        return Carbon::parse($this->tasks()->whereNull('completed_at')->max('due_date'))->addDays(3);
    }

    public function getMinutesTakenAttribute()
    {
        $tasks = $this->tasks()
            ->whereNotNull('estimate')
            ->whereNotNull('completed_at')
            ->where('due_date', '<=', now()->endOfDay())
            ->get();

        $minutes = 0;

        foreach ($tasks as $task) {
            $minutes += $task->minutes_taken;
        }

        return $minutes;
    }

    public function getHmsAttribute()
    {
        return Timesheet::toHMS($this->minutes_taken);
    }

    public function getPerformanceAttribute()
    {
        $tasks = $this->tasks()
            ->whereNotNull('estimate')
            ->whereNotNull('completed_at')
            ->where('due_date', '<=', now()->endOfDay())
            ->get();

        $performances = [];
        foreach ($tasks as $task) {
            $e = $task->performance;

            if ($e > -1) {
                $performances[] = $e;
            }
        }

        if (count($performances) == 0) {
            return 0;
        }

        // \Log::debug([count($performances)]);
        return sprintf("%.2f", array_sum($performances) / count($performances));
    }
}
