<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use Spatie\Tags\Tag as BaseTag;

class Tag extends BaseTag
{
    public function tasks()
    {
        return $this->morphedByMany(Task::class, 'taggable');
    }

    public function getIncompleteTasksCountAttribute(){
        return $this->tasks()->whereNull('completed_at')->count();
    }

    public function getDueDateAttribute(){
        return Carbon::parse($this->tasks()->whereNull('completed_at')->max('due_date'))->addDays(3);
    }
}
