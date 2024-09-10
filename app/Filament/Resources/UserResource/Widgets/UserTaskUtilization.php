<?php

namespace App\Filament\Resources\UserResource\Widgets;

use App\Models\Task;
use App\Models\Timesheet;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;

class UserTaskUtilization extends BaseWidget
{
    public $user;
    public $filterData;

    protected int | string | array $columnSpan = 12;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn() => $this->fetchData())
            ->columns([
                TextColumn::make('estimate_label')
                    ->label('Estimate'),
                TextColumn::make('no_tasks')
                    ->label('No. of Tasks'),
                TextColumn::make('time_taken')
                    ->label('Avg. Time Taken / Task'),
                TextColumn::make('utilization')
                    ->label('Utilization %'),
            ]);
    }

    public function getTableRecordKey(Model $record): string
    {
        return 'estimate';
    }

    public function fetchData()
    {
        return new Task();
    }

    public function getTableRecords(): EloquentCollection | Paginator | CursorPaginator
    {
        $estimates = config('options.estimate');
        
        $data = [];

        foreach ($estimates as $estimate => $estimateLabel) {
            $tasks = $this->user->tasks()->select('id')
                ->where('estimate', $estimate)
                ->whereDate('completed_at', '>=', $this->filterData['startDate'])
                ->whereDate('completed_at', '<=', $this->filterData['endDate'])
                ->get();

            $averageTimePerTask = Timesheet::select('user_id', \DB::raw('AVG(TIMESTAMPDIFF(MINUTE, start_at, end_at)) AS time'))
                ->whereNotNull('start_at')
                ->whereNotNull('end_at')
                ->where('user_id', $this->user->id)
                ->whereIn('task_id', $tasks->pluck('id'))
                ->groupBy('user_id')
                ->first();


            $task = new Task([
                'estimate' => $estimate,
            ]);
            $task->no_tasks = count($tasks);
            $task->time_taken = (float) sprintf("%.2f", $averageTimePerTask->time);
            $task->utilization = (float) sprintf("%.2f", (($task->time_taken / $estimate) * 100));

            $data[] = $task;
        }

        return new EloquentCollection($data);
    }
}
