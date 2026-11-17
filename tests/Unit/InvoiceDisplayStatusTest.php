<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceDisplayStatusTest extends TestCase
{
    use RefreshDatabase;

    private function makeInvoice(string $number, array $overrides = []): Invoice
    {
        $client = Client::create([
            'billing_name' => "Status Client {$number}",
            'nickname' => 'SC',
        ]);

        return Invoice::create(array_merge([
            'invoice_no' => $number,
            'date' => '2026-05-01',
            'client_id' => $client->id,
        ], $overrides));
    }

    public function test_every_series_other_than_si_is_treated_as_a_proforma(): void
    {
        foreach (['DH-00001/2026', 'SR-00001/2026', 'RE-00001/2026', 'PRO-00001/2026'] as $number) {
            $invoice = $this->makeInvoice($number);

            $this->assertSame('PROFORMA', $invoice->type, $number);
            $this->assertSame('open', $invoice->display_status, $number);
        }

        $taxInvoice = $this->makeInvoice('SI-00001/2026');

        $this->assertSame('TAX', $taxInvoice->type);
    }

    public function test_a_proforma_is_open_until_it_is_converted_to_a_tax_invoice(): void
    {
        $proforma = $this->makeInvoice('SR-00001/2026');

        $this->assertSame('open', $proforma->display_status);
        $this->assertSame(Invoice::STATUS_OPEN, $proforma->status);

        $proforma->createTaxInvoice();

        $this->assertSame('converted', $proforma->fresh()->display_status);
    }

    public function test_a_tax_invoice_is_open_until_paid_and_then_closed(): void
    {
        $taxInvoice = $this->makeInvoice('SI-00001/2026');

        $this->assertSame('open', $taxInvoice->display_status);

        $taxInvoice->markAsPaid('2026-05-02');
        $taxInvoice->refresh();

        $this->assertSame('closed', $taxInvoice->display_status);
        $this->assertSame(Invoice::STATUS_CLOSED, $taxInvoice->status);

        $taxInvoice->revertPaidStatus();
        $taxInvoice->refresh();

        $this->assertSame('open', $taxInvoice->display_status);
        $this->assertSame(Invoice::STATUS_OPEN, $taxInvoice->status);
    }

    public function test_void_overrides_the_derived_status_for_both_invoice_types(): void
    {
        $proforma = $this->makeInvoice('SR-00002/2026');
        $proforma->void('Client cancelled');

        $taxInvoice = $this->makeInvoice('SI-00002/2026', ['date' => now()->toDateString()]);
        $taxInvoice->void('Raised in error');

        $this->assertSame('void', $proforma->fresh()->display_status);
        $this->assertSame('void', $taxInvoice->fresh()->display_status);
    }
}
