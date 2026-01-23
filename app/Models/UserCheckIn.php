<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserCheckIn extends Model
{
    use HasFactory;

    protected $table = 'user_checkins';

    protected $guarded = [];

    protected $casts = [
        'punch_at' => 'datetime',
    ];

    public function user(){
        return $this->belongsTo(User::class);
    }
}
