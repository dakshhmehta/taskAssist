<?php

namespace Ri\Accounting\Filament\Accounting\Pages;

use Filament\Pages\Page;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Ri\Accounting\Helper;
use Romininteractive\Transaction\Transaction;

class GeneralLedger extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationGroup = 'Reports';
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?int $navigationSort = 1;

    protected static string $view = 'ri.accounting.filament.accounting.pages.general-ledger';

    public function table(Table $table): Table
    {
        return $table
            ->query(Transaction::query()->whereNull('type')->orderBy('date', 'asc'))
            ->columns([
                TextColumn::make('account.name'),
                TextColumn::make('date')
                    ->date('d-m-Y'),
                TextColumn::make('debit')
                    ->formatStateUsing(fn($state) => abs($state))
                    ->extraAttributes(['class' => 'dr-cell']),
                TextColumn::make('credit')
                    ->formatStateUsing(fn($state) => abs($state))
                    ->extraAttributes(['class' => 'cr-cell']),
            ])
            ->filters([
                // SelectFilter::make('account_id')
                //     ->relationship('account', 'name')
                //     ->label('Filter by Account'),
                // Filter::make('date')
                //     ->form([
                //         Tables\Components\DatePicker::make('start_date')->label('Start Date'),
                //         Tables\Components\DatePicker::make('end_date')->label('End Date'),
                //     ])
                //     ->query(fn ($query, $data) =>
                //         $query->when($data['start_date'], fn ($q) => $q->where('created_at', '>=', $data['start_date']))
                //               ->when($data['end_date'], fn ($q) => $q->where('created_at', '<=', $data['end_date']))
                //     ),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
