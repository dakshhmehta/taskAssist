<?php

namespace App\Models;

use App\Traits\CustomLogOptions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;

class Timesheet extends Model
{
    use HasFactory;

    use CustomLogOptions, LogsActivity;

    protected $guarded = [];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function scopeWorking($query){
        $query->whereNotNull('start_at')->whereNull('end_at');
    }

    public static function toHMS($minutes)
    {
        $hours = str_pad(intdiv($minutes, 60), 2, "0", STR_PAD_LEFT); // Get the number of hours
        $minutes = str_pad($minutes % 60, 2, "0", STR_PAD_LEFT);      // Get the remaining minutes

        $formatted = '';
        if ($hours >= 0) {
            $formatted .= "{$hours}:";
        }
        if ($minutes >= 0) {
            $formatted .= "{$minutes}";
        }

        return $formatted ?: '0'; // Return '0m' if no hours or minutes
    }
}
