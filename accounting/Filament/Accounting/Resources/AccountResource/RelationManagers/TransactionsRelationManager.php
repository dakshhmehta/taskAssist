<?php

namespace Ri\Accounting\Filament\Accounting\Resources\AccountResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Ri\Accounting\Helper;
use Romininteractive\Transaction\Transaction;

class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('date')
                    ->sortable()
                    ->searchable()
                    ->dateTime('d/m/Y'),
                TextColumn::make('relateds'),
                TextColumn::make('description')
                    ->searchable(),
                TextColumn::make('amount')
                    ->formatStateUsing(fn($state) => Helper::accountBalance($state))
                    ->sortable(),
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
