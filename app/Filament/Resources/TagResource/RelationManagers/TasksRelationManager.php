<?php

namespace App\Filament\Resources\TagResource\RelationManagers;

use App\Jobs\ScheduleTasksForUser;
use App\Models\Task;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Actions\Action;
use Filament\Tables\Enums\ActionsPosition;
use Illuminate\Support\Facades\Auth;
use Parallax\FilamentComments\Tables\Actions\CommentsAction;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class TasksRelationManager extends RelationManager
{
    protected static string $relationship = 'tasks';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('assignee_id')
                    ->label('Assignee')
                    ->options(User::where('is_disabled', false)->pluck('name', 'id'))
                    ->default(\Auth::user()->id)
                    ->required(),
                Forms\Components\TextInput::make('title')
                    ->required(),
                Forms\Components\Toggle::make('is_important')
                    ->label('Is Important?')
                    ->default(true)
                    ->required(),
                Forms\Components\Toggle::make('is_urgent')
                    ->label('Urgent?')
                    ->default(false)
                    ->required(),
                Select::make('estimate')
                    ->label('Estimate')
                    ->options(config('options.estimate')),
                Forms\Components\Toggle::make('auto_schedule')
                    ->live()
                    ->default(true)
                    ->required(),
                Forms\Components\DateTimePicker::make('due_date')
                    ->hidden(fn($get) => $get('auto_schedule')),

                Forms\Components\Toggle::make('is_recurring')
                    ->label('Recurring Task')
                    ->live()
                    ->default(false),

                Section::make('Recurrence Settings')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('recurrence_type')
                                    ->label('Repeat')
                                    ->options([
                                        'daily' => 'Daily',
                                        'weekly' => 'Weekly',
                                        'monthly' => 'Monthly',
                                        'yearly' => 'Yearly',
                                    ])
                                    ->live()
                                    ->required(),
                                Forms\Components\TextInput::make('recurrence_interval')
                                    ->label('Every')
                                    ->numeric()
                                    ->minValue(1)
                                    ->default(1)
                                    ->suffix(fn($get) => match ($get('recurrence_type')) {
                                        'daily' => 'day(s)',
                                        'weekly' => 'week(s)',
                                        'monthly' => 'month(s)',
                                        'yearly' => 'year(s)',
                                        default => '',
                                    })
                                    ->required(),
                            ]),
                        Select::make('recurrence_days')
                            ->label('On Days')
                            ->options([
                                0 => 'Sunday',
                                1 => 'Monday',
                                2 => 'Tuesday',
                                3 => 'Wednesday',
                                4 => 'Thursday',
                                5 => 'Friday',
                                6 => 'Saturday',
                            ])
                            ->multiple()
                            ->visible(fn($get) => $get('recurrence_type') === 'weekly'),
                        Grid::make(2)
                            ->schema([
                                Forms\Components\DatePicker::make('recurrence_end_date')
                                    ->label('End Date')
                                    ->nullable()
                                    ->minDate(now()),
                                Forms\Components\TextInput::make('recurrence_max_occurrences')
                                    ->label('End After')
                                    ->numeric()
                                    ->minValue(1)
                                    ->nullable(),
                            ]),
                    ])
                    ->collapsible()
                    ->visible(fn($get) => $get('is_recurring')),

            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable(),
                Tables\Columns\TextColumn::make('due_date')
                    ->label('Due Date')
                    ->dateTime('d-m-Y')
                    ->description(fn(Task $task) => $task->completed_at)
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_important')
                    ->label('Important?')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_urgent')
                    ->label('Urgent?')
                    ->boolean(),
                TextColumn::make('estimate_label')
                    ->label('Estimate'),
                Tables\Columns\TextColumn::make('assignee.name')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('assignee_id')
                    ->options(User::query()
                        ->when(! Auth::user()->is_admin, fn($query) => $query->where('is_disabled', false))
                        ->pluck('name', 'id')),
                TernaryFilter::make('completed')
                    ->label('Completed?')
                    ->placeholder('All')
                    ->trueLabel('Completed')
                    ->falseLabel('Incomplete')
                    ->default(false)
                    ->queries(
                        true: fn(Builder $query) => $query->whereNotNull('completed_at'),
                        false: fn(Builder $query) => $query->whereNull('completed_at'),
                        blank: fn(Builder $query) => $query,
                    ),

                Filter::make('completed_date_range')
                    ->form([
                        Forms\Components\DatePicker::make('completed_date_from')
                            ->label('Completed Date From')
                            ->placeholder('Select Start Date'),
                        Forms\Components\DatePicker::make('completed_date_to')
                            ->label('Completed Date To')
                            ->placeholder('Select End Date'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when($data['completed_date_from'], function (Builder $query, $date) {
                                return $query->whereDate('completed_at', '>=', $date);
                            })
                            ->when($data['completed_date_to'], function (Builder $query, $date) {
                                return $query->whereDate('completed_at', '<=', $date);
                            });
                    })
                    ->label('Completed Date Range'),

                TernaryFilter::make('planned')
                    ->label('Planned?')
                    ->placeholder('All')
                    ->trueLabel('Yes')
                    ->falseLabel('Not yet')
                    ->default(true)
                    ->queries(
                        true: fn(Builder $query) => $query->whereNotNull('estimate'),
                        false: fn(Builder $query) => $query->whereNull('estimate'),
                        blank: fn(Builder $query) => $query,
                    ),
                TernaryFilter::make('ignored')
                    ->label('Archived?')
                    ->placeholder('Without Archived')
                    ->trueLabel('Archived Only')
                    ->falseLabel('Without Archived')
                    ->queries(
                        true: fn(Builder $query) => $query->withoutGlobalScope('excludeIgnored')->whereNotNull('ignored_at'),
                        false: fn(Builder $query) => $query->whereNull('ignored_at'),
                        blank: fn(Builder $query) => $query->whereNull('ignored_at'),
                    ),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
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
                CommentsAction::make(),
                Action::make('markCompleted')
                    ->label('Complete')
                    ->action(fn(Task $task) => $task->complete())
                    ->visible(fn(Task $task) => $task->isCompletable())
                    ->color('success'),
                Tables\Actions\EditAction::make()
                    ->visible(fn(Task $task) => ! $task->is_completed)
                    ->using(function($record, $data){
                        $record->update($data);

                        dispatch(new ScheduleTasksForUser($record->assignee_id));
                    }),
                // Tables\Actions\DeleteAction::make(),
            ], position: ActionsPosition::BeforeColumns)
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('massEdit')
                        ->label('Mass Edit')
                        ->icon('heroicon-o-pencil-square')
                        ->form([
                            Select::make('assignee_id')
                                ->label('Assignee')
                                ->options(User::query()
                                    ->when(!Auth::user()->is_admin, fn($query) => $query->where('is_disabled', false))
                                    ->pluck('name', 'id'))
                                ->placeholder('No change'),
                            Forms\Components\Toggle::make('is_urgent')
                                ->label('Urgent?'),
                            Forms\Components\Toggle::make('is_important')
                                ->label('Important?'),
                        ])
                        ->action(function (\Illuminate\Support\Collection $records, array $data): void {
                            foreach ($records as $record) {
                                if (isset($data['assignee_id']) && $data['assignee_id'] !== null) {
                                    $record->assignee_id = $data['assignee_id'];
                                }
                                $record->is_urgent = $data['is_urgent'];
                                $record->is_important = $data['is_important'];
                                $record->save();
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('archive')
                        ->label('Archive')
                        ->icon('heroicon-o-archive-box')
                        ->action(function (\Illuminate\Support\Collection $records) {
                            $assigneeIds = $records->pluck('assignee_id')->unique();
                            $records->each(fn($record) => $record->ignore());
                            foreach ($assigneeIds as $id) {
                                dispatch(new \App\Jobs\ScheduleTasksForUser($id));
                            }
                        })
                        ->requiresConfirmation()
                        ->color('warning')
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('unarchive')
                        ->label('Unarchive')
                        ->icon('heroicon-o-archive-box-arrow-down')
                        ->action(function (\Illuminate\Support\Collection $records) {
                            $assigneeIds = $records->pluck('assignee_id')->unique();
                            $records->each(fn($record) => $record->unIgnore());
                            foreach ($assigneeIds as $id) {
                                dispatch(new \App\Jobs\ScheduleTasksForUser($id));
                            }
                        })
                        ->requiresConfirmation()
                        ->color('success')
                        ->deselectRecordsAfterCompletion(),
                    ExportBulkAction::make()
                        ->exports([
                            ExcelExport::make()
                                ->askForFilename()
                                ->withColumns([
                                    Column::make('title')->heading('Task'),
                                    Column::make('completed_at')->heading('Completed On'),
                                    Column::make('hms')->heading('H:M:S'),
                                    Column::make('minutes_taken')->heading('Time Taken'),
                                    Column::make('cost')->heading('Amount'),
                                ])
                        ]),
                ])->visible(Auth::user()->is_admin),
            ]);
    }
}
