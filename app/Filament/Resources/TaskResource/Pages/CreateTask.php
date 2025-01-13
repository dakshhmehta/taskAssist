<?php

namespace App\Filament\Resources\TaskResource\Pages;

use App\Filament\Resources\TaskResource;
use App\Jobs\ScheduleTasksForUser;
use App\Notifications\NewTaskAssignedNotification;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateTask extends CreateRecord
{
    protected static string $resource = TaskResource::class;

    protected function afterCreate(): void
    {
        \Session::put('last_assignee_id', $this->record->assignee_id);

        dispatch(new ScheduleTasksForUser($this->record->assignee_id));

        if ($this->record->assignee_id != Auth::user()->id) {
            $this->record->assignee->notify(new NewTaskAssignedNotification($this->record));
        }
    }
}
