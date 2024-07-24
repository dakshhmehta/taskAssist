<?php

namespace App\Models;

use App\Jobs\ScheduleTasksForUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Spatie\Tags\HasTags;

class Task extends Model
{
    use HasFactory;
    use HasTags;

    protected $dates = ['completed_at'];

    protected $casts = [
        'due_date' => 'datetime',
    ];

    protected $guarded = [];

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function canStartWork($userId)
    {
        return ((!$this->isTimeStarted($userId)) && // Timer is not started
            $this->assignee_id == $userId && // Assigned to current user
            $this->due_date != null && // Is Scheduled
            $this->is_completed == false); // Is not a completed task
    }

    public function getEstimateLabelAttribute()
    {
        if (!$this->estimate) return null;

        $options = config('options.estimate');

        return $options[$this->estimate];
    }

    public function getIsCompletedAttribute()
    {
        return ($this->completed_at != null);
    }

    public function complete()
    {
        $this->completed_at = now();
        $this->save();
    }

    public function timesheet()
    {
        return $this->hasMany(Timesheet::class);
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
}
