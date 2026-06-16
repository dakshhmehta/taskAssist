<?php

namespace App\Filament\Widgets;

use App\Models\Task;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\DB;
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
                $scheduledIds = Task::query()
                    ->select('id')
                    ->where('assignee_id', $userId)
                    ->whereNotNull('due_date')
                    ->whereNull('completed_at')
                    ->orderBy('due_date', 'ASC')
                    ->limit(5);

                $scheduled = Task::query()
                    ->select('tasks.*', DB::raw('0 as is_ticking'))
                    ->whereIn('id', $scheduledIds);

                $ticking = Task::query()
                    ->select('tasks.*', DB::raw('1 as is_ticking'))
                    ->where('assignee_id', $userId)
                    ->whereNull('completed_at')
                    ->whereHas('timesheet', function ($q) use ($userId) {
                        $q->where('user_id', $userId)->whereNull('end_at');
                    });

                return Task::query()
                    ->fromSub($ticking->union($scheduled), 'upcoming_tasks')
                    ->select('upcoming_tasks.*')
                    ->orderByDesc('is_ticking')
                    ->orderByRaw('due_date IS NULL ASC')
                    ->orderBy('due_date', 'ASC');
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
