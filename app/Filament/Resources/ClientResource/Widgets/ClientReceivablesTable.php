<?php

namespace App\Filament\Resources\ClientResource\Widgets;

use App\Models\Client;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Parallax\FilamentComments\Tables\Actions\CommentsAction;

class ClientReceivablesTable extends BaseWidget
{
    protected int | string | array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Client Receivables')
            ->query(
                Client::whereHas('invoices', function ($q) {
                    $q->whereNull('paid_date');
                })
            )
            ->paginated(false)
            ->defaultSort('receivable_amount', 'DESC')
            ->columns([
                TextColumn::make('receivable_amount')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('display_name')
                    ->label('Client'),
            ])
            ->actions([
                CommentsAction::make(),
            ]);
    }
}
