<?php

namespace App\Filament\Resources\TaskResource\Pages;

use App\Filament\Resources\TaskResource;
use App\Jobs\ScheduleTasksForUser;
use App\Models\Task;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Parallax\FilamentComments\Actions\CommentsAction;

class EditTask extends EditRecord
{
    protected static string $resource = TaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            CommentsAction::make(),

            Action::make('markCompleted')
                ->label('Complete')
                ->action(fn(Task $task) => $task->complete())
                ->visible(fn(Task $task) => $task->isCompletable())
                ->color('success'),
        ];
    }

    protected function afterSave(): void
    {
        dispatch(new ScheduleTasksForUser($this->record->assignee_id));
    }
}
