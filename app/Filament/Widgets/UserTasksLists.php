<?php

namespace App\Filament\Widgets;

use App\Models\Task;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class UserTasksLists extends BaseWidget
{
    protected $userId;

    protected int | string | array $columnSpan = 12;
    protected static ?int $sort = 1;

    public function __construct($userId = null)
    {
        $this->userId = $userId;
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
            // TextColumn::make('due_date')
            // ->label('Due Date')
            // ->dateTime('d-m-Y'),
            // TextColumn::make('estimate_label')
            //     ->label('Estimate')
        ])
        ->actions([
            Action::make('markCompleted')
                ->label('Complete')
                ->action(fn(Task $task) => $task->complete())
                ->visible(fn(Task $task) => !$task->is_completed)
                ->color('success'),
            // Tables\Actions\EditAction::make()
            //     ->visible(fn(Task $task) => ! $task->is_completed),
            // DeleteAction::make()
            //     ->visible(fn(Task $task) => ! $task->is_completed),
        ], position: ActionsPosition::BeforeColumns);
    }
}
