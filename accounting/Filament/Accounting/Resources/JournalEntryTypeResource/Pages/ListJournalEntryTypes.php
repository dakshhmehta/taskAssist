<?php

namespace Ri\Accounting\Filament\Accounting\Resources\JournalEntryTypeResource\Pages;

use Ri\Accounting\Filament\Accounting\Resources\JournalEntryTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListJournalEntryTypes extends ListRecords
{
    protected static string $resource = JournalEntryTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
