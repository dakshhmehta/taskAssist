<?php

namespace App\Filament\Widgets;

use App\Models\Task;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Parallax\FilamentComments\Tables\Actions\CommentsAction;

class MyUpcomingTasks extends BaseWidget
{
    protected int | string | array $columnSpan = 12;
    protected static ?int $sort = 1;

    public function table(Table $table): Table
    {
        return $table
            ->query(function () {
                $userId = \Auth::user()->id;

                return Task::query()
                    ->where('assignee_id', $userId)
                    ->whereNull('completed_at');
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

    public function getTableRecords(): EloquentCollection | Paginator | CursorPaginator
    {
        if ($this->cachedTableRecords) {
            return $this->cachedTableRecords;
        }

        $userId = \Auth::user()->id;

        $tickingTasks = Task::query()
            ->where('assignee_id', $userId)
            ->whereNull('completed_at')
            ->whereHas('timesheet', function ($q) use ($userId) {
                $q->where('user_id', $userId)->whereNull('end_at');
            })
            ->get();

        $scheduledTasks = Task::query()
            ->where('assignee_id', $userId)
            ->whereNotNull('due_date')
            ->whereNull('completed_at')
            ->whereNotIn('id', $tickingTasks->pluck('id'))
            ->orderBy('due_date', 'ASC')
            ->limit(max(0, 6 - $tickingTasks->count()))
            ->get();

        return $this->cachedTableRecords = $tickingTasks
            ->concat($scheduledTasks)
            ->values();
    }
}
