<?php

namespace App\Models;

use App\Jobs\ScheduleTasksForUser;
use App\Traits\IgnorableTrait;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;

use Spatie\Tags\Tag as BaseTag;

// TODO: Implement a freezing capability: Once freezed, any task that exists or will be created, 
// will be scheduled only once, and than Auto Schedule will be marked as OFF

class Tag extends BaseTag
{
    use IgnorableTrait;

    protected $fillable = ['name', 'slug', 'type', 'order_column', 'cost'];

    protected static function booted()
    {
        static::deleting(function (Tag $tag) {
            if (DB::table('taggables')->where('tag_id', $tag->id)->count() > 0) {
                return false;
            }
        });
    }

    public function ignore()
    {
        $this->ignored_at = now();
        $this->save();

        $assigneeIds = [];

        $this->tasks()
            ->whereNull('completed_at')
            ->get()
            ->each(function ($task) use (&$assigneeIds) {
                $task->ignore();
                $assigneeIds[] = $task->assignee_id;
            });

        foreach (array_unique($assigneeIds) as $id) {
            dispatch(new ScheduleTasksForUser($id));
        }
    }

    public function unIgnore()
    {
        $this->ignored_at = null;
        $this->save();

        $assigneeIds = [];

        Task::withoutGlobalScope('excludeIgnored')
            ->whereHas('tags', fn($q) => $q->where('tags.id', $this->id))
            ->get()
            ->each(function ($task) use (&$assigneeIds) {
                $task->unIgnore();
                $assigneeIds[] = $task->assignee_id;
            });

        foreach (array_unique($assigneeIds) as $id) {
            dispatch(new ScheduleTasksForUser($id));
        }
    }

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

    public function getHourlyCostAttribute()
    {
        return $this->cost ?: config('settings.company_hourly_rate');
    }
}
