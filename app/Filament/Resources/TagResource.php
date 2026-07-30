<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TagResource\Pages;
use App\Filament\Resources\TagResource\RelationManagers\TasksRelationManager;
use App\Models\Tag;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class TagResource extends Resource
{
    protected static ?string $model = Tag::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationGroup = 'Tasks';
    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('cost')
                    ->numeric()
                    ->label('Hourly Cost')
                    ->prefix('Rs')
                    ->visible(fn() => Gate::allows('viewCost', Tag::class)),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('index')
                    ->label('#')
                    ->rowIndex(),

                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('incomplete_tasks_count')
                    ->label('Incomplete #'),
                TextColumn::make('performance')
                    ->label('Performance'),
                TextColumn::make('hms')
                    ->label('Time Taken'),
                TextColumn::make('due_date')
                    ->dateTime('d-m-Y'),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                //
            ])
            ->actions([
                // Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->action(function (\Illuminate\Support\Collection $records) {
                            $deleted = 0;

                            foreach ($records as $record) {
                                if (Gate::denies('delete', $record)) {
                                    Notification::make()
                                        ->danger()
                                        ->title("Cannot delete tag '{$record->name}' — tag has associated tasks.")
                                        ->send();
                                    continue;
                                }

                                $record->delete();
                                $deleted++;
                            }

                            if ($deleted > 0) {
                                Notification::make()
                                    ->success()
                                    ->title("{$deleted} tag(s) deleted successfully.")
                                    ->send();
                            }
                        }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            TasksRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTags::route('/'),
            'create' => Pages\CreateTag::route('/create'),
            'view' => Pages\ViewTag::route('/{record}'),
            'edit' => Pages\EditTag::route('/{record}/edit'),
        ];
    }
}
