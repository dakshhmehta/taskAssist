<?php

namespace App\Models;

use App\Traits\CustomLogOptions;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;

class Holiday extends Model
{
    use HasFactory;
    use CustomLogOptions, LogsActivity;

    protected $guarded = [];

    public static function isHoliday(Carbon $date){
        $holiday = static::where('date', $date->format('Y-m-d'))->exists();

        return $holiday;
    }
}
