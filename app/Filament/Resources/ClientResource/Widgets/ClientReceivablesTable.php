<?php

namespace App\Filament\Resources\ClientResource\Widgets;

use App\Models\Client;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
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
            ->columns([
                TextColumn::make('receivable_amount')
                    ->numeric()
                    ->formatStateUsing(function ($state) {
                        // Mask all characters except the last two
                        $length = strlen((int) $state);
                        return str_repeat('*', max(1, $length - 2)) . substr((int) $state, -2);
                    })
                    ->tooltip(fn ($state) => $state)
                    ->sortable(),
                TextColumn::make('display_name')
                    ->label('Client'),
            ])
            ->actions([
                CommentsAction::make(),
            ]);
    }
}
