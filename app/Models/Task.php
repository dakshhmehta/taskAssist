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

class Task extends Model implements HasMedia
{
    use InteractsWithMedia;
    use HasFactory;
    use HasTags;

    use InteractsWithMediaFolders;

    use CustomLogOptions;
    use LogsActivity;

    use HasFilamentComments;

    protected $dates = ['completed_at'];

    protected $casts = [
        'due_date' => 'datetime',
    ];

    protected $touches = ['tags'];

    protected $guarded = ['files'];

    protected static function boot()
    {
        parent::boot();
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

        // TODO: Fire Event for sending notification
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
        $cost = ($this->minutes_taken  * config('settings.company_hourly_rate')) / 60;

        return sprintf("%.2f", $cost);
    }
}
