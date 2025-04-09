<?php

namespace Ri\Accounting\Filament\Accounting\Pages;

use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Pages\Page;
use Ri\Accounting\Filament\Accounting\Widgets\AssetsTable;
use Ri\Accounting\Filament\Accounting\Widgets\LiabilitiesTable;

class BalanceSheet extends Page
{
    use HasFiltersForm;

    protected static ?string $title = 'Balance Sheet';
    protected static ?string $navigationGroup = 'Reports';
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?int $navigationSort = 4;

    protected static string $view = 'ri.accounting.filament.accounting.pages.balancesheet';

    public function getWidgets(): array
    {
        $data = ['filterData' => $this->filters];

        return [
            LiabilitiesTable::make(),
            AssetsTable::make(),
        ];
    }

    public function getVisibleWidgets(): array
    {
        return $this->filterVisibleWidgets($this->getWidgets());
    }

    /**
     * @return int | string | array<string, int | string | null>
     */
    public function getColumns(): int | string | array
    {
        return 12;
    }
}
