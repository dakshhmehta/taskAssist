<?php

namespace App\Filament\Widgets;

use App\Models\Task;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Parallax\FilamentComments\Tables\Actions\CommentsAction;

class MyUpcomingTasks extends BaseWidget
{
    protected int | string | array $columnSpan = 12;
    protected static ?int $sort = 1;

    public function table(Table $table): Table
    {
        return $table
            ->query(function () {
                $tasks = Task::where('assignee_id', \Auth::user()->id)
                    ->whereNotNull('due_date')
                    ->whereNull('completed_at')
                    ->orderBy('due_date', 'ASC')->limit(5);

                return $tasks;
            })
            ->paginated(false)
            ->columns([
                TextColumn::make('display_title')
                    ->label('Title'),
                TextColumn::make('due_date')
                    ->label('Due Date')
                    ->dateTime('d-m-Y h:i A'),
            ])
            ->recordUrl(
                fn(Task $record): string => route('filament.admin.resources.tasks.edit', ['record' => $record]),
            )
            ->actions([
                Action::make('startTime')
                    ->label('Start')
                    ->action(fn(Task $task) => $task->startTimer())
                    ->visible(fn(Task $task) => $task->canStartWork(\Auth::user()->id))
                    ->color('info'),

                Action::make('stopTime')
                    ->label('Stop')
                    ->action(fn(Task $task) => $task->endTimer())
                    ->visible(fn(Task $task) => $task->isTimeStarted(\Auth::user()->id))
                    ->color('warning'),

                CommentsAction::make(),


                Action::make('markCompleted')
                    ->label('Complete')
                    ->action(fn(Task $task) => $task->complete())
                    ->visible(fn(Task $task) => $task->isCompletable())
                    ->color('success'),
            ]);
    }
}
