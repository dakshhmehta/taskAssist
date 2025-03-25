<?php

namespace Ri\Accounting\Filament\Accounting\Resources\AccountResource\Pages;

use Ri\Accounting\Filament\Accounting\Resources\AccountResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAccount extends EditRecord
{
    protected static string $resource = AccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
