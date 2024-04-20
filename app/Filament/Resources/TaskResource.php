<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TaskResource\Pages;
use App\Filament\Resources\TaskResource\RelationManagers;
use App\Models\Task;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TaskResource extends Resource
{
    protected static ?string $model = Task::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
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
                Forms\Components\Toggle::make('auto_schedule')
                    ->live()
                    ->default(true)
                    ->required(),
                Forms\Components\DateTimePicker::make('due_date')
                    ->hidden(fn($get) => $get('auto_schedule')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('assignee_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('title')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_important')
                    ->label('Important?')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_urgent')
                    ->label('Urgent?')
                    ->boolean(),
                Tables\Columns\TextColumn::make('due_date')
                    ->label('Due Date')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\IconColumn::make('auto_schedule')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->boolean(),
                Tables\Columns\TextColumn::make('completed_at')
                    ->dateTime()
                    ->sortable(),
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
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'edit' => Pages\EditTask::route('/{record}/edit'),
        ];
    }
}
