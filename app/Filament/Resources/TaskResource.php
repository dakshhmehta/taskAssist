<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TaskResource\Pages;
use App\Filament\Resources\TaskResource\Pages\ViewTask;
use App\Models\Task;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieTagsInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\ActionsPosition;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Session;
use Parallax\FilamentComments\Tables\Actions\CommentsAction;

class TaskResource extends Resource
{
    protected static ?string $model = Task::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        $userId = \Auth::user()->id;
        $lastUserId = Session::get('last_assignee_id', null);
        if ($lastUserId) {
            $userId = $lastUserId;
        }

        return $form
            ->schema([
                Select::make('assignee_id')
                    ->label('Assignee')
                    ->options(User::all()->pluck('name', 'id'))
                    ->default($userId)
                    ->required(),
                Forms\Components\TextInput::make('title')
                    ->required(),
                Select::make('estimate')
                    ->label('Estimate')
                    ->options(config('options.estimate'))
                    ->visible(fn(?Task $task): bool => Gate::allows('changeEstimate', $task)),
                MarkdownEditor::make('description'),
                Forms\Components\Toggle::make('is_important')
                    ->label('Is Important?')
                    ->default(true)
                    ->required(),
                Forms\Components\Toggle::make('is_urgent')
                    ->label('Urgent?')
                    ->default(false)
                    ->required(),
                Forms\Components\Toggle::make('auto_schedule')
                    ->live()
                    ->default(true)
                    ->required(),
                Forms\Components\DateTimePicker::make('due_date')
                    ->hidden(fn($get) => $get('auto_schedule')),
                SpatieTagsInput::make('tags')
                    ->columnSpanFull()
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable(),
                Tables\Columns\TextColumn::make('due_date')
                    ->label('Due Date')
                    ->dateTime('d-m-Y H:i A')
                    ->sortable(),
                TextColumn::make('hms')
                    ->label('Time Taken'),
                Tables\Columns\IconColumn::make('is_important')
                    ->label('Important?')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_urgent')
                    ->label('Urgent?')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_completed')
                    ->label('Completed?')
                    ->boolean(),
                TextColumn::make('estimate_label')
                    ->label('Estimate'),
                Tables\Columns\TextColumn::make('assignee.name')
                    ->sortable(),
                Tables\Columns\IconColumn::make('auto_schedule')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('assignee_id')
                    ->options(User::all()->pluck('name', 'id'))
                    ->default(\Auth::user()->id),
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
            ->actions([
                ViewAction::make('view')
                    // ->url(fn(Task $task) => route('filament.admin.resources.tasks.view', ['record' => $task]))
                    ->slideOver(),
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
                // Tables\Actions\EditAction::make()
                //     ->visible(fn(Task $task) => ! $task->is_completed),
                // DeleteAction::make()
                //     ->visible(fn(Task $task) => ! $task->is_completed),
            ], position: ActionsPosition::BeforeColumns)
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTasks::route('/'),
            'create' => Pages\CreateTask::route('/create'),
            // 'view' => ViewTask::route('/{record}'),
            'edit' => Pages\EditTask::route('/{record}/edit'),
        ];
    }
}
