<?php

namespace Ri\Accounting\Filament\Accounting\Resources\AccountResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('description')
                    ->searchable(),
                TextColumn::make('related_acount')
                ->formatStateUsing(function ($state, $record) {
                    dd($state, $record);
                    /** @var \App\Models\Transaction $record */
                    return $record->getRelatedAccount($this->getOwnerRecord())?->name;
                }), 
                TextColumn::make('amount')
                    ->money('INR'),
                TextColumn::make('created_at')
                    ->dateTime('d/m/Y H:i:s'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                // Tables\Actions\EditAction::make(),
                // Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                // Tables\Actions\BulkActionGroup::make([
                //     Tables\Actions\DeleteBulkAction::make(),
                // ]),
            ]);
    }
}
