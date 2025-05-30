<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Traits\CustomLogOptions;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Romininteractive\Transaction\Traits\HasTransactions;
use Spatie\Activitylog\Traits\LogsActivity;

class User extends Authenticatable
{
    use CustomLogOptions;
    use HasFactory, Notifiable, LogsActivity;

    use HasTransactions;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',

        'work_hours',
        'salary',

        'salary_type',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function leaves()
    {
        return $this->hasMany(UserLeave::class);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class, 'assignee_id');
    }

    public function timesheet()
    {
        return $this->hasMany(Timesheet::class);
    }

    public function getPerformanceAttribute()
    {
        $tasks = $this->tasks()
            ->where('assignee_id', $this->id)
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

    public function getIsAdminAttribute()
    {
        return $this->id == 1;
    }

    // public function getPerformanceAttribute()
    // {
    //     if ($this->utilization <= 100) {
    //         return 10;
    //     }

    //     $performance = ((100 - ($this->utilization - 100)) / 10);

    //     if ($performance < 0) {
    //         $performance = 0;
    //     }

    //     return sprintf("%.2f", $performance);
    // }

    public function adjustStar($star, $remarks)
    {
        if ($star == null) {
            return false;
        }

        if ($star > 0) {
            $transaction = $this->credit($star, now(), $remarks);
            $transaction->changeType('star');
        } else if ($star < 0) {
            $transaction = $this->debit($star, now(), $remarks);
            $transaction->changeType('star');
        }
    }

    public function getStarsAttribute()
    {
        return (int) $this->balance(['star']);
    }

    public function stars($since)
    {
        $stars = $this->transactions(['star'])
            ->where('date', '>=', $since)
            ->sum('amount');

        return (int) $stars;
    }

    public function timeWorkedThisWeek($offset = 0)
    {
        $totalTimeWorked = Timesheet::select('user_id', \DB::raw('SUM(TIMESTAMPDIFF(MINUTE, start_at, end_at)) AS time'))
            ->whereNotNull('start_at')
            ->whereNotNull('end_at')
            ->where('end_at', '>=', now()->addWeeks($offset)->startOfWeek())
            ->where('end_at', '<=', now()->addWeeks($offset)->endOfWeek())
            ->where('user_id', $this->id)
            ->groupBy('user_id')
            ->first();

        if (! $totalTimeWorked) {
            return 0;
        }

        return (int) $totalTimeWorked->time;
    }

    public function performanceThisWeekTimeBased($offset = 0)
    {
        $timeWorked = $this->timeWorkedThisWeek($offset);
        $expectedWorkingHrs = $this->work_hours * 5;

        $performance = (float) sprintf("%.2f", (($timeWorked / $expectedWorkingHrs) * 10));

        return $performance;
    }

    public function performanceThisWeekTaskBased($offset = 0)
    {
        if ($this->timeWorkedThisWeek($offset) <= 0) {
            return 0;
        }

        $tasks = $this->tasks()
            ->where('assignee_id', $this->id)
            ->whereNotNull('estimate')
            ->where('completed_at', '>=', now()->addWeeks($offset)->startOfWeek())
            ->where('completed_at', '<=', now()->addWeeks($offset)->endOfWeek())
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

        $taskBasedPerformance = array_sum($performances) / count($performances);

        return $taskBasedPerformance;
    }

    public function performanceThisWeek($offset = 0)
    {
        $taskBasedPerformance = $this->performanceThisWeekTaskBased($offset);
        $timeBasedPerformance = $this->performanceThisWeekTimeBased($offset);
        $performance = ($taskBasedPerformance + $timeBasedPerformance) / 2;

        // \Log::debug([count($performances)]);
        return sprintf("%.2f", $performance);
    }

    public function isOnLeave(Carbon $date)
    {
        $leave =  $this->leaves()
            ->where('status', 'APPROVED')
            ->where('from_date', '<=', $date->format('Y-m-d'))
            ->where('to_date', '>=', $date->format('Y-m-d'))
            ->exists();

        return $leave;
    }

    public function hasCreditedCLForMonth(Carbon $date)
    {
        $hasCLCredited = $this->transactions(['cl'])
            ->where('date', '=', $date->format('Y-m-d'))
            ->exists();

        return $hasCLCredited;
    }

    public function creditCLForMonth(Carbon $date)
    {
        if($this->hasCreditedCLForMonth($date)){
            return false;
        }

        $clTxn = $this->credit(1, $date, 'CL');
        $clTxn->changeType('cl');
    }

    public function getCLAttribute()
    {
        return (int) $this->balance(['cl']);
    }
}
