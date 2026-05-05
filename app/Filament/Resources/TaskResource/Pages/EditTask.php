<?php

namespace App\Filament\Resources\TaskResource\Pages;

use App\Filament\Resources\TaskResource;
use App\Jobs\ScheduleTasksForUser;
use App\Models\Task;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
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

            Action::make('startTime')
                ->label('Start')
                ->action(fn(Task $task) => $task->startTimer())
                ->visible(fn(Task $task) => $task->canStartWork(Auth::user()->id))
                ->color('info'),

            Action::make('stopTime')
                ->label('Stop')
                ->action(fn(Task $task) => $task->endTimer())
                ->visible(fn(Task $task) => $task->isTimeStarted(Auth::user()->id))
                ->color('warning'),

            Action::make('archive')
                ->label('Archive')
                ->icon('heroicon-o-archive-box')
                ->action(fn(Task $task) => $task->ignore())
                ->visible(fn(Task $task) => !$task->isIgnored())
                ->requiresConfirmation()
                ->color('warning'),

            Action::make('unarchive')
                ->label('Unarchive')
                ->icon('heroicon-o-archive-box-arrow-down')
                ->action(fn(Task $task) => $task->unIgnore())
                ->visible(fn(Task $task) => $task->isIgnored())
                ->requiresConfirmation()
                ->color('success'),
        ];

    }

    protected function afterSave(): void
    {
        dispatch(new ScheduleTasksForUser($this->record->assignee_id));
    }
}
