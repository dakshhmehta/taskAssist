<?php

namespace App\Models;

use App\Jobs\ScheduleTasksForUser;
use App\Traits\CustomLogOptions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Traits\LogsActivity;

class UserLeave extends Model
{
    use HasFactory, LogsActivity, CustomLogOptions;

    protected $guarded = [];

    protected $casts = [
        'approved_at' => 'datetime',
        'from_date' => 'date',
        'to_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::saving(function (UserLeave $leave) {
            if ($leave->user_id == null) {
                $leave->user_id = Auth::user()->id;
            }
        });
    }

    public function getLeaveDaysAttribute()
    {
        return $this->to_date->diffInDays($this->from_date) + 1;
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function approvedByUser()
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function approve()
    {
        $this->approved_at = now();
        $this->status = 'APPROVED';
        $this->approved_by_user_id = Auth::user()->id;
        $this->admin_remarks = null;

        dispatch(new ScheduleTasksForUser($this->user_id));

        return $this->save();
    }

    public function reject($reason)
    {
        $this->approved_at = now();
        $this->status = 'REJECTED';
        $this->approved_by_user_id = Auth::user()->id;
        $this->admin_remarks = $reason;

        return $this->save();
    }
}
