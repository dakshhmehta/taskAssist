<?php

namespace App\Filament\Resources\UserLeaveResource\Pages;

use App\Filament\Resources\UserLeaveResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUserLeaves extends ListRecords
{
    protected static string $resource = UserLeaveResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
