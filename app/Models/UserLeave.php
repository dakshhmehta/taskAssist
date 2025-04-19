<?php

namespace App\Models;

use App\Jobs\ScheduleTasksForUser;
use App\Traits\CustomLogOptions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Romininteractive\Transaction\Traits\HasTransactions;
use Spatie\Activitylog\Traits\LogsActivity;

class UserLeave extends Model
{
    use HasFactory, LogsActivity, CustomLogOptions, HasTransactions;

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

        static::saved(function (UserLeave $leave) {
            $leave->transactions()->delete();

            if ($leave->status == 'APPROVED' and $leave->code == 'CL') {
                $txn = $leave->user->debit($leave->leave_days, $leave->from_date, 'Leave application accepted from ' . $leave->from_date->format('d-m-Y') . ' to ' . $leave->to_date->format('d-m-Y'));
                $txn->changeType('cl');
                $txn->associate($leave);
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

        $saved = $this->save();

        dispatch(new ScheduleTasksForUser($this->user_id));

        return $saved;
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
