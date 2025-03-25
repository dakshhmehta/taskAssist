<?php

namespace Ri\Accounting\Filament\Accounting\Resources\JournalEntryTypeResource\Pages;

use Ri\Accounting\Filament\Accounting\Resources\JournalEntryTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateJournalEntryType extends CreateRecord
{
    protected static string $resource = JournalEntryTypeResource::class;
}
