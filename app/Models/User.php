<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Traits\CustomLogOptions;
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


    public function tasks()
    {
        return $this->hasMany(Task::class, 'assignee_id');
    }

    public function timesheet()
    {
        return $this->hasMany(Timesheet::class);
    }

    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = Hash::make($value);
    }

    public function getUtilizationAttribute()
    {
        $tasks = $this->tasks()
            ->where('assignee_id', $this->id)
            ->whereNotNull('estimate')
            ->whereNotNull('completed_at')
            ->where('due_date', '<=', now()->endOfDay())
            ->get();

        $efficiencies = [];
        foreach ($tasks as $task) {
            $minutes = $task->minutes_taken;
            if ($minutes > 0) {
                \Log::debug([$task->title . ' ', $minutes . ' - ' . $task->estimate]);
                $efficiencies[] = (($minutes * 100) / $task->estimate);
            }
        }

        if (count($efficiencies) == 0) {
            return 0;
        }

        \Log::debug([count($efficiencies)]);
        return sprintf("%.2f", array_sum($efficiencies) / count($efficiencies));
    }

    public function getIsAdminAttribute()
    {
        return $this->id == 1;
    }

    public function getPerformanceAttribute()
    {
        if ($this->utilization <= 100) {
            return 10;
        }

        $performance = ((100 - ($this->utilization - 100)) / 10);

        if ($performance < 0) {
            $performance = 0;
        }

        return sprintf("%.2f", $performance);
    }

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

    public function performanceThisWeek()
    {
        if ($this->timeWorkedThisWeek() <= 0) {
            return 0;
        }

        $tasks = $this->tasks()
            ->where('assignee_id', $this->id)
            ->whereNotNull('estimate')
            ->where('completed_at', '>=', now()->startOfWeek())
            ->where('completed_at', '<=', now()->endOfWeek())
            ->get();

        $efficiencies = [];
        foreach ($tasks as $task) {
            $minutes = $task->minutes_taken;
            if ($minutes > 0) {
                $efficiencies[] = (($minutes * 100) / $task->estimate);
            }
        }

        if (count($efficiencies) == 0) {
            return 0;
        }

        $efficiencyThisWeek = sprintf("%.2f", array_sum($efficiencies) / count($efficiencies));

        if ($efficiencyThisWeek <= 100) {
            return 10;
        }

        $performance = ((100 - ($efficiencyThisWeek - 100)) / 10);

        if ($performance < 0) {
            $performance = 0;
        }

        return (float) sprintf("%.2f", $performance);
    }

    public function timeWorkedThisWeek()
    {
        $totalTimeWorked = Timesheet::select('user_id', \DB::raw('SUM(TIMESTAMPDIFF(MINUTE, start_at, end_at)) AS time'))
            ->whereNotNull('start_at')
            ->whereNotNull('end_at')
            ->where('end_at', '>=', now()->startOfWeek())
            ->where('end_at', '<=', now()->endOfWeek())
            ->where('user_id', $this->id)
            ->groupBy('user_id')
            ->first();

        if (! $totalTimeWorked) {
            return 0;
        }

        return (int) $totalTimeWorked->time;
    }
}
