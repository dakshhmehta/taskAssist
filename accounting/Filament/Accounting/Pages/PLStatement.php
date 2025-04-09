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
use Ri\Accounting\Models\Account;
use Romininteractive\Transaction\Transaction;
use Spatie\Color\Hex;

class PLStatement extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $title = 'P&L Statement';
    protected static ?string $navigationGroup = 'Reports';
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'ri.accounting.filament.accounting.pages.pl-statement';

    public function getTableRecords(): EloquentCollection | Paginator | CursorPaginator
    {
        $data = Account::whereIn('type', ['Revenue', 'Expense'])->get()->map(function ($acc) {
            return new Account([
                'type' => $acc->type,
                'name' => $acc->name,
                'credit' => $acc->totalCredit(),
                'debit' => $acc->totalDebit(),
            ]);
        })->filter(function ($acc) {
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
                TextColumn::make('name')
                    ->formatStateUsing(function (Account $acc) {
                        $color = $acc->type == 'Revenue' ? '#00ff00' : '#ff0000';
                        return "<span style='color: {$color}'>{$acc->name}</span>";
                    })->html(),
                TextColumn::make('debit')
                    ->formatStateUsing(fn($state) => abs($state))
                    ->extraAttributes(['class' => 'dr-cell']),
                TextColumn::make('credit')
                    ->formatStateUsing(fn($state) => abs($state))
                    ->extraAttributes(['class' => 'cr-cell']),
            ])
            ->filters([])
            ->defaultSort('created_at', 'desc');
    }
}
