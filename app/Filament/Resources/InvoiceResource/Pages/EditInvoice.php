<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use App\Models\Invoice;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                    // ->icon('printer')
                    ->label('Print')
                    ->url(fn(Invoice $invoice): string => route('invoices.print', [$invoice->id, 'force' => 1]), true),
            Actions\DeleteAction::make(),
        ];
    }
}
