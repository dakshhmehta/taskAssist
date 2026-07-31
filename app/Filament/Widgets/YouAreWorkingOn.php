<?php

namespace App\Filament\Widgets;

use App\Models\Task;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Parallax\FilamentComments\Tables\Actions\CommentsAction;

class YouAreWorkingOn extends BaseWidget
{
    protected int | string | array $columnSpan = 12;
    protected static ?int $sort = 0;

    public static function canView(): bool
    {
        $userId = \Auth::user()->id;

        return Task::query()
            ->where('assignee_id', $userId)
            ->whereNull('completed_at')
            ->whereHas('timesheet', function ($q) use ($userId) {
                $q->where('user_id', $userId)->whereNull('end_at');
            })
            ->exists();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function () {
                $userId = \Auth::user()->id;

                return Task::query()
                    ->where('assignee_id', $userId)
                    ->whereNull('completed_at')
                    ->whereHas('timesheet', function ($q) use ($userId) {
                        $q->where('user_id', $userId)->whereNull('end_at');
                    });
            })
            ->paginated(false)
            ->heading('You are working on')
            ->columns([
                TextColumn::make('display_title')
                    ->label('Title'),
            ])
            ->recordUrl(
                fn(Task $record): string => route('filament.admin.resources.tasks.edit', ['record' => $record]),
            )
            ->actions([
                Action::make('stopTime')
                    ->label('Stop')
                    ->action(fn(Task $task) => $task->endTimer())
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

        return $this->cachedTableRecords = Task::query()
            ->where('assignee_id', $userId)
            ->whereNull('completed_at')
            ->whereHas('timesheet', function ($q) use ($userId) {
                $q->where('user_id', $userId)->whereNull('end_at');
            })
            ->get();
    }
}
