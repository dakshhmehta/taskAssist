<?php

namespace App\Filament\Resources\HostingPackageResource\Pages;

use App\Filament\Resources\HostingPackageResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListHostingPackages extends ListRecords
{
    protected static string $resource = HostingPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
