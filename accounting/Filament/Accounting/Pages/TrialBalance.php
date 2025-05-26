<?php

namespace Ri\Accounting\Filament\Accounting\Pages;

use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Ri\Accounting\Helper;
use Ri\Accounting\Models\Account;
use Romininteractive\Transaction\Transaction;

class TrialBalance extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationGroup = 'Reports';
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?int $navigationSort = 2;

    protected static string $view = 'ri.accounting.filament.accounting.pages.trial-balance';

    public function getTableRecords(): EloquentCollection | Paginator | CursorPaginator
    {
        $data = Account::all()->map(function ($acc) {
            return new Account([
                'name' => $acc->name,
                'credit' => $acc->totalCredit(),
                'debit' => $acc->totalDebit(),
            ]);
        })->filter(function($acc){
            return ($acc['credit'] != 0 || $acc['debit'] != 0);
        });

        return new EloquentCollection($data);
    }

    public function getTableRecordKey(Model $record): string
    {
        return 'name';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Transaction::query())
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('debit')
                    ->formatStateUsing(fn($state) => Helper::accountBalance($state))
                    ->extraAttributes(['class' => 'dr-cell']),
                TextColumn::make('credit')
                    ->formatStateUsing(fn($state) => Helper::accountBalance($state))
                    ->extraAttributes(['class' => 'cr-cell']),
            ])
            ->filters([
            ])
            ->defaultSort('created_at', 'desc');
    }
}
