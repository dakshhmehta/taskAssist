<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use Filament\Actions;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Actions\ActionGroup as ActionsActionGroup;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make('dh-invoice')
                ->label('New DH Invoice')
                ->url(fn() => InvoiceResource::getUrl('create', ['prefix' => 'DH-'])),
            Actions\CreateAction::make('service-invoice')
                ->label('New Service Invoice')
                ->url(fn() => InvoiceResource::getUrl('create', ['prefix' => 'SR-'])),
        ];
    }
}
