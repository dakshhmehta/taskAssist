<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory;

    protected $dates = ['completed_at'];

    protected $guarded = [];

    public function assignee(){
        return $this->belongsTo(User::class, 'assignee_id');
    }
}
