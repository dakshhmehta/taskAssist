<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use App\Models\Invoice;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

    public function mount(): void
    {
        parent::mount();

        // Only set the invoice number for the create form
        $this->form->fill([
            'invoice_no' => Invoice::nextInvoiceNumber(),
        ]);
    }
}
