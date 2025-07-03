<?php

namespace App\Traits;

use App\Models\Invoice;
use Carbon\Carbon;
use Ri\Accounting\Models\Account;

trait TaxableInvoice
{
    public function proformaInvoice()
    {
        return $this->belongsTo(Invoice::class, 'proforma_invoice_id');
    }

    public function taxInvoice()
    {
        return $this->hasOne(Invoice::class, 'proforma_invoice_id');
    }

    public function hasTaxInvoice(){
        return static::where('proforma_invoice_id', $this->id)->exists();
    }

    public function createTaxInvoice(): Invoice
    {
        // Ensure only Proforma invoices can be converted
        if ($this->type !== 'PROFORMA') {
            throw new \Exception("Only Proforma Invoice can be converted to Tax Invoice.");
        }

        // Clone the base invoice
        $taxInvoice = $this->replicate();
        $taxInvoice->invoice_no = Invoice::nextInvoiceNumber('SI-');
        $taxInvoice->created_at = now();
        $taxInvoice->updated_at = now();
        $taxInvoice->proforma_invoice_id = $this->id;
        $taxInvoice->save();

        // Clone items with remaining amount
        foreach ($this->items as $item) {
            $newItem = $item->replicate();
            $newItem->invoice_id = $taxInvoice->id;
            $newItem->proforma_invoice_id = $this->id;

            $newItem->save();
        }

        // Clone items with remaining amount
        foreach ($this->extras as $extra) {
            $extraItem = $extra->replicate();
            $extraItem->invoice_id = $taxInvoice->id;
            $newItem->proforma_invoice_id = $this->id;

            $extraItem->save();
        }

        return $taxInvoice;
    }

    protected function getRevenueAccount() {}

    public function createAccountingEntries(Account $revenueAccount)
    {
        if ($this->type != 'TAX') {
            throw new \Exception('Only TAX INVOICE can have accounting entries.');
        }

        $this->transactions()->delete();

        $customerAccount = $this->client->account;

        if (! $customerAccount) {
            throw new \Exception('Customer account not found');
        }

        $t1 = $customerAccount->credit($this->total, $this->date); // Positive is Dr
        $t1->associate($this);

        // GST
        if ($this->date->gte(config('app.gstin_start_date'))) {
            $igst = $this->igst;
            $cgst = $this->cgst;
            $sgst = $this->sgst;

            if ($igst > 0) {
                $igstAccount = Account::where('name', 'IGST Payable')->firstOrFail();

                if ($igstAccount) {
                    $t3 = $igstAccount->debit($igst, $this->date); // Negetive is Cr, debit to make negetive entry
                    $t3->associate($this);
                }
            }

            if ($cgst > 0) {
                $cgstAccount = Account::where('name', 'CGST Payable')->firstOrFail();

                if ($cgstAccount) {
                    $t4 = $cgstAccount->debit($cgst, $this->date);
                    $t4->associate($this);
                }
            }

            if ($sgst > 0) {
                $sgstAccount = Account::where('name', 'SGST Payable')->firstOrFail();

                if ($sgstAccount) {
                    $t5 = $sgstAccount->debit($sgst, $this->date);
                    $t5->associate($this);
                }
            }

            $t2 = $revenueAccount->debit($this->total - $igst - $cgst - $sgst, $this->date);
            $t2->associate($this);
        } else {
            $t2 = $revenueAccount->debit($this->total, $this->date);
            $t2->associate($this);
        }
    }
}
