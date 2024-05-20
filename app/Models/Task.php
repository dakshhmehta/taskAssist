<?php

namespace App\Models;

use App\Jobs\ScheduleTasksForUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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

    public function assignee(){
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function getEstimateLabelAttribute(){
        if(! $this->estimate) return null;

        $options = config('options.estimate');

        return $options[$this->estimate];
    }

    public function getIsCompletedAttribute(){
        return ($this->completed_at != null);
    }

    public function complete(){
        $this->completed_at = now();
        $this->save();
    }
}
