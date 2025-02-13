<?php

namespace App\Filament\Resources\UserResource\Widgets;

use App\Models\Task;
use App\Models\Timesheet;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;

use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;


class UserTasksTable extends BaseWidget
{
    public $user;
    public $filterData;

    protected int | string | array $columnSpan = 12;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn() => $this->fetchData())
            ->columns([
                TextColumn::make('tag')
                    ->label('Project')
                    ->searchable(),
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('description')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                TextColumn::make('due_date')
                    ->label('Due Date')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->dateTime('d-m-Y H:i A')
                    ->sortable(),
                TextColumn::make('estimate_label')
                    ->label('Estimate'),
                TextColumn::make('hms')
                    ->label('Time Taken'),
                TextColumn::make('performance')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exports([
                            ExcelExport::make()
                                ->askForFilename()
                                ->fromTable()
                        ])
                ])->visible(\Auth::user()->is_admin)
            ]);
    }

    public function fetchData()
    {
        return $this->user->tasks()
            ->completedOnly()
            ->whereDate('completed_at', '>=', $this->filterData['startDate'])
            ->whereDate('completed_at', '<=', $this->filterData['endDate']);
    }

    // public function getTableRecords(): EloquentCollection | Paginator | CursorPaginator
    // {
    //     $estimates = config('options.estimate');

    //     $data = [];

    //     foreach ($estimates as $estimate => $estimateLabel) {
    //         $tasks = $this->user->tasks()->select('id')
    //             ->where('estimate', $estimate)
    //             ->whereDate('completed_at', '>=', $this->filterData['startDate'])
    //             ->whereDate('completed_at', '<=', $this->filterData['endDate'])
    //             ->get();

    //         $averageTimePerTask = Timesheet::select('user_id', \DB::raw('SUM(TIMESTAMPDIFF(MINUTE, start_at, end_at)) AS time'))
    //             ->whereNotNull('start_at')
    //             ->whereNotNull('end_at')
    //             ->where('user_id', $this->user->id)
    //             ->whereIn('task_id', $tasks->pluck('id'))
    //             ->groupBy('user_id')
    //             ->first();


    //         $task = new Task([
    //             'estimate' => $estimate,
    //         ]);
    //         $task->no_tasks = count($tasks);
    //         $task->time_taken = (float) sprintf("%.2f", (($averageTimePerTask) ? $averageTimePerTask->time : 0)) / $task->no_tasks;
    //         $task->utilization = (float) sprintf("%.2f", (($task->time_taken / $estimate) * 100));

    //         $data[] = $task;
    //     }

    //     return new EloquentCollection($data);
    // }
}
