<?php

namespace Ri\Accounting\Filament\Accounting\Resources\JournalEntryResource\Pages;

use Ri\Accounting\Filament\Accounting\Resources\JournalEntryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListJournalEntries extends ListRecords
{
    protected static string $resource = JournalEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
