<?php

namespace App\Filament\Resources\TaskResource\Widgets;

use App\Models\Task;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;

class UserWorkingTaskList extends BaseWidget
{
    protected static ?int $sort = 1;
    protected int | string | array $columnSpan = 12;

    protected static ?string $heading = 'Team - Working On';

    public static function canView(): bool
    {
        return Auth::user()->is_admin;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                function() {
                    $tasks = Task::whereHas('timesheet', function($q){
                        $q->working();
                    })
                        ->orderBy('assignee_id')
                        ->orderBy('due_date', 'ASC');
            
                    return $tasks;
                }
            )
            ->paginated(false)
            ->columns([
                TextColumn::make('assignee.name'),
                TextColumn::make('title'),    
            ]);
    }
}
