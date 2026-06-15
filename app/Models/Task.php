<?php

namespace App\Models;

use App\Jobs\ScheduleTasksForUser;
use App\Traits\CustomLogOptions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Parallax\FilamentComments\Models\Traits\HasFilamentComments;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Tags\HasTags;
use TomatoPHP\FilamentMediaManager\Traits\InteractsWithMediaFolders;
use App\Traits\IgnorableTrait;
use Illuminate\Database\Eloquent\Builder;


class Task extends Model implements HasMedia
{
    use InteractsWithMedia;
    use HasFactory;
    use HasTags;

    use InteractsWithMediaFolders;

    use CustomLogOptions;
    use LogsActivity;

    use HasFilamentComments;
    use IgnorableTrait;


    protected $dates = ['completed_at'];

    protected $casts = [
        'due_date' => 'datetime',
        'is_recurring' => 'boolean',
        'recurrence_interval' => 'integer',
        'recurrence_days' => 'array',
        'recurrence_end_date' => 'date',
        'recurrence_occurrences_count' => 'integer',
        'recurrence_max_occurrences' => 'integer',
    ];

    protected $touches = ['tags'];

    protected $guarded = ['files'];

    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope('excludeIgnored', function (Builder $builder) {
            $builder->whereNull('ignored_at');
        });
    }


    public function assignee()
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function lastComment(){
        return $this->filamentComments()->latest()->first();
    }

    public function canStartWork($userId)
    {
        return ((!$this->isTimeStarted($userId)) && // Timer is not started
            $this->assignee_id == $userId && // Assigned to current user
            $this->due_date != null && // Is Scheduled
            $this->is_completed == false); // Is not a completed task
    }

    public function getTagAttribute()
    {
        $tag = $this->tags()->first();

        if (! $tag) {
            return null;
        }

        return $tag->name;
    }

    public function getDisplayTitleAttribute()
    {
        $tag = $this->tag;

        if (! $tag) {
            return $this->title;
        }

        return '[' . $tag . '] ' . $this->title;
    }

    public function getEstimateLabelAttribute()
    {
        if (!$this->estimate) {
            return null;
        }

        $options = config('options.estimate');

        return $options[$this->estimate];
    }

    public function getIsCompletedAttribute()
    {
        return ($this->completed_at != null);
    }

    public function getPerformanceAttribute($val)
    {
        $minutes = $this->minutes_taken;

        if ($minutes == 0) {
            return -1;
        }

        $utilization =  ($minutes / $this->estimate);

        if ($utilization <= 1) {
            $performance = 1;
        } else {
            $performance = ((1 - ($utilization - 1)));

            if ($performance < 0) {
                $performance = 0;
            }
        }

        return sprintf("%.2f", $performance * 10);
    }

    public function scopeCompletedOnly($q)
    {
        $q->whereNotNull('completed_at');
    }

    public function complete()
    {
        $this->completed_at = now();
        $this->save();

        if ($this->is_recurring) {
            $this->createNextRecurrence();
        }

        // TODO: Fire Event for sending notification
    }

    public function createNextRecurrence()
    {
        if (!$this->is_recurring || !$this->recurrence_type) {
            return null;
        }

        if ($this->recurrence_end_date && now()->startOfDay()->gt($this->recurrence_end_date)) {
            return null;
        }

        if ($this->recurrence_max_occurrences && $this->recurrence_occurrences_count >= $this->recurrence_max_occurrences) {
            return null;
        }

        $nextDueDate = $this->calculateNextDueDate();

        if (!$nextDueDate) {
            return null;
        }

        $newTask = $this->replicate();
        $newTask->due_date = $nextDueDate;
        $newTask->completed_at = null;
        $newTask->ignored_at = null;
        $newTask->recurrence_occurrences_count = $this->recurrence_occurrences_count + 1;
        $newTask->save();

        $this->tags->each(fn($tag) => $newTask->attachTag($tag));

        dispatch(new ScheduleTasksForUser($newTask->assignee_id));

        return $newTask;
    }

    public function calculateNextDueDate(): ?\Carbon\Carbon
    {
        $baseDate = $this->due_date ?? now();
        $interval = $this->recurrence_interval ?: 1;

        return match ($this->recurrence_type) {
            'daily' => $baseDate->copy()->addDays($interval),
            'weekly' => $this->nextWeeklyDate($baseDate, $interval),
            'monthly' => $baseDate->copy()->addMonths($interval),
            'yearly' => $baseDate->copy()->addYears($interval),
            default => null,
        };
    }

    protected function nextWeeklyDate(\Carbon\Carbon $baseDate, int $interval): ?\Carbon\Carbon
    {
        $days = $this->recurrence_days;

        if (empty($days)) {
            return $baseDate->copy()->addWeeks($interval);
        }

        $currentDayOfWeek = (int) $baseDate->format('w');
        $targetDay = collect($days)
            ->sort()
            ->first(fn($day) => (int) $day > $currentDayOfWeek);

        if ($targetDay !== null) {
            $daysUntil = (int) $targetDay - $currentDayOfWeek;
            return $baseDate->copy()->addDays($daysUntil);
        }

        $firstDay = collect($days)->sort()->first();
        $daysUntil = 7 - $currentDayOfWeek + (int) $firstDay;
        $daysUntil += (($interval - 1) * 7);

        return $baseDate->copy()->addDays($daysUntil);
    }

    public function timesheet()
    {
        return $this->hasMany(Timesheet::class);
    }

    public function getInProgressAttribute()
    {
        return ((bool) $this->timesheet()->working()->count() == 1);
    }

    public function isCompletable()
    {
        return !$this->is_completed &&  // Is not completed
            ($this->assignee_id == Auth::user()->id || Auth::user()->is_admin) && // Or is admin
            $this->in_progress == false // Task is not being worked upon
        ;
    }

    public function isTimeStarted($userId)
    {
        $hasTimerStarterd = Timesheet::where('user_id', $userId)
            ->where('task_id', $this->id)
            ->whereNull('end_at')->exists();

        return $hasTimerStarterd;
    }

    public function startTimer()
    {
        $userId = Auth::user()->id;

        $hasTimerStarterd = Timesheet::where('user_id', $userId)->whereNull('end_at')->exists();

        if (!$hasTimerStarterd) {
            $timesheet = new Timesheet([
                'user_id' => $userId,
                'task_id' => $this->id,
                'start_at' => now(),
            ]);

            return $timesheet->save();
        }

        return false;
    }

    public function endTimer()
    {
        $userId = Auth::user()->id;

        $startedTimesheet = Timesheet::where('task_id', $this->id)
            ->where('user_id', $userId)
            ->whereNull('end_at')->first();

        if ($startedTimesheet) {
            $startedTimesheet->end_at = now();

            return $startedTimesheet->save();
        }

        return false;
    }

    public function getMinutesTakenAttribute()
    {
        $timesheet = $this->timesheet()->whereNotNull('end_at')->get();

        $minutes = 0;

        foreach ($timesheet as $entry) {
            $minutes += $entry->end_at->diffInMinutes($entry->start_at);
        }

        return $minutes;
    }

    public function getHmsAttribute()
    {
        return Timesheet::toHMS($this->minutes_taken);
    }

    public function getCostAttribute()
    {
        $tag = $this->tags()->first();
        $hourlyRate = $tag ? $tag->hourly_cost : config('settings.company_hourly_rate');
        
        $cost = ($this->minutes_taken * $hourlyRate) / 60;

        return sprintf("%.2f", $cost);
    }
}
