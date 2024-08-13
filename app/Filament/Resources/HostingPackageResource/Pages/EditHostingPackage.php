<?php

namespace App\Filament\Resources\HostingPackageResource\Pages;

use App\Filament\Resources\HostingPackageResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditHostingPackage extends EditRecord
{
    protected static string $resource = HostingPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
