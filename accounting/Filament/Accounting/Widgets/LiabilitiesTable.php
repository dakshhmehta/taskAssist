<?php

namespace Ri\Accounting\Filament\Accounting\Widgets;

use Dom\Attr;
use Filament\Tables;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\Summarizers\Summarizer;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Ri\Accounting\Models\Account;

class LiabilitiesTable extends BaseWidget
{
    protected int | string | array $columnSpan = 6;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Liabilities')
            ->defaultSort('name', 'asc')
            ->query(Account::query())
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('balance')
                    ->formatStateUsing(fn($state) => abs($state))
            ])
            ->paginated(false);
    }

    public function getTableRecords(): Collection | Paginator | CursorPaginator
    {
        $data = Account::whereIn('type', ['Asset', 'Liability'])->get()->map(function ($acc) {
            return new Account([
                'type' => $acc->type,
                'name' => $acc->name,
                'balance' => $acc->balance(),
            ]);
        })->filter(function ($acc) {
            return ($acc['balance'] < 0);
        });

        $pl = $this->getPL();

        if($pl < 0){ // Profit is Negetive because Cr > Dr
            $data->push(new Account([
                'name' => 'Realized Loss',
                'is_summary' => true,
                'balance' => $pl,
            ]));    
        }

        $data->push(new Account([
            'name' => 'Total',
            'is_summary' => true,
            'balance' => $data->sum('balance'),
        ]));

        return new Collection($data);
    }

    public function getTableRecordKey(Model $record): string
    {
        return 'name';
    }

    public function getPL()
    {
        $total = 0;

        Account::whereIn('type', ['Revenue', 'Expense'])->get()->map(function ($acc) use(&$total) {
            $total += $acc->balance();
        });

        return $total;
    }
}
