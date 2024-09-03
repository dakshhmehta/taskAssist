<?php

namespace App\Models;

use App\Traits\CustomLogOptions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;

class Email extends Model
{
    use HasFactory;

    use LogsActivity, CustomLogOptions;

    protected $guarded = [];

    protected $casts = [
        'expiry_date' => 'datetime',
    ];
}
