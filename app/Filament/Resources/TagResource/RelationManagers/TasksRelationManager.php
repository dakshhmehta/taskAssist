<?php

namespace App\Filament\Resources\TagResource\RelationManagers;

use App\Models\Task;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Actions\Action;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Filters\Filter;
use Illuminate\Support\Facades\Auth;
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
                    ->options(User::all()->pluck('name', 'id'))
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
                    )
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Action::make('markCompleted')
                    ->label('Complete')
                    ->action(fn(Task $task) => $task->complete())
                    ->visible(fn(Task $task) => !$task->is_completed)
                    ->color('success'),
                Tables\Actions\EditAction::make(),
                // Tables\Actions\DeleteAction::make(),
            ], position: ActionsPosition::BeforeColumns)
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
                        ])
                ])->visible(Auth::user()->is_admin),
            ]);
    }
}
