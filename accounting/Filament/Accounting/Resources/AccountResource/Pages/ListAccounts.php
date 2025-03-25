<?php

namespace Ri\Accounting\Filament\Accounting\Resources\AccountResource\Pages;

use Ri\Accounting\Filament\Accounting\Resources\AccountResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAccounts extends ListRecords
{
    protected static string $resource = AccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
