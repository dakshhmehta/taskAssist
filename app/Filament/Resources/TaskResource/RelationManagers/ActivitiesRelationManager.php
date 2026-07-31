<?php

namespace App\Filament\Resources\TaskResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Spatie\Activitylog\Models\Activity;

class ActivitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'activities';

    protected static ?string $recordTitleAttribute = 'description';

    protected static ?string $title = 'Activity Log';

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d-m-Y H:i:s')
                    ->sortable(),

                TextColumn::make('description')
                    ->label('Description')
                    ->searchable(),

                TextColumn::make('causer.name')
                    ->label('By')
                    ->placeholder('System'),

                TextColumn::make('properties')
                    ->label('Changes')
                    ->formatStateUsing(function (Activity $record): string {
                        $old = $record->properties['old'] ?? [];
                        $new = $record->properties['attributes'] ?? [];

                        if (empty($old) && empty($new)) {
                            return $record->description;
                        }

                        $changes = [];
                        foreach ($new as $key => $value) {
                            $oldValue = $old[$key] ?? null;
                            if ($oldValue === $value) {
                                continue;
                            }
                            if (is_bool($value)) {
                                $oldValue = $oldValue ? 'Yes' : 'No';
                                $value = $value ? 'Yes' : 'No';
                            }
                            $changes[] = "{$key}: {$value} ← {$oldValue}";
                        }

                        return implode(', ', $changes);
                    })
                    ->wrap(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
