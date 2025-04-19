<?php

namespace App\Filament\Resources\UserResource\Widgets;

use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class UserLeaveLedgerTable extends BaseWidget
{
    public $user;

    public function table(Table $table): Table
    {
        return $table
            ->heading(false)
            ->query(
                fn() => $this->user->transactions()->whereIn('type', ['cl'])
            )
            ->columns([
                TextColumn::make('date')
                    ->dateTime('d-m-Y'),
                TextColumn::make('type'),
                TextColumn::make('description'),
                TextColumn::make('amount'),
            ])
            ->defaultSort('date', 'asc');
    }
}
