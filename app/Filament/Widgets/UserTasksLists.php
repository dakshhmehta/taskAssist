<?php

namespace App\Filament\Widgets;

use App\Models\Task;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;

class UserTasksLists extends BaseWidget
{
    protected $userId;

    protected int | string | array $columnSpan = 12;
    protected static ?int $sort = 3;

    public function __construct($userId = null)
    {
        $this->userId = $userId;
    }

    public static function canView(): bool
    {
        return Auth::user()->is_admin;
    }

    public function table(Table $table): Table
    {
        $date = now();

        if($date->isWeekend()){
            do {
                $date = $date->addDay();
            } while($date->isWeekend());
        }

        return $table
        ->query(function() use($date) {
            $tasks = Task::where('due_date', '<=', $date->endOfDay()->format('Y-m-d H:i:s'))
                ->whereNotNull('due_date')
                ->whereNull('completed_at')
                ->orderBy('assignee_id')
                ->orderBy('due_date', 'ASC');
    
            return $tasks;
        })
        ->heading('Tasks for '.$date->format('d-m-Y'))
        ->paginated(false)
        ->columns([
            TextColumn::make('assignee.name'),
            TextColumn::make('title'),
        ])
        ->recordUrl(
            fn (Task $record): string => route('filament.admin.resources.tasks.edit', ['record' => $record]),
        )
        ->actions([
            Action::make('markCompleted')
                ->label('Complete')
                ->action(fn(Task $task) => $task->complete())
                ->visible(fn(Task $task) => !$task->is_completed)
                ->color('success'),
        ]);
    }
}
