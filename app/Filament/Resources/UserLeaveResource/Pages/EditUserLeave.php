<?php

namespace App\Filament\Resources\UserLeaveResource\Pages;

use App\Filament\Resources\UserLeaveResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUserLeave extends EditRecord
{
    protected static string $resource = UserLeaveResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
