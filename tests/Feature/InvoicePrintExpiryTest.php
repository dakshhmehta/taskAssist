<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Domain;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePrintExpiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_print_view_reads_the_invoice_line_expiry_not_the_asset_expiry(): void
    {
        $client = Client::create(['billing_name' => 'Print Term Client', 'nickname' => 'PTC']);

        // The asset expires in 2027; the advance invoice line was shifted to 2028.
        $domain = Domain::create([
            'tld' => 'printterm.com',
            'client_id' => $client->id,
            'expiry_date' => '2027-06-15',
        ]);

        $invoice = Invoice::create([
            'invoice_no' => 'DH-PRINT-1',
            'date' => '2026-06-15',
            'client_id' => $client->id,
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'itemable_type' => Domain::class,
            'itemable_id' => $domain->id,
            'price' => 2920,
            'expiry_date' => '2028-06-15',
        ]);

        $response = $this->get(route('invoices.print', [$invoice->id, 'view' => 1]));

        $response->assertOk();
        $response->assertSee('Next domain and hosting renewal: June, 2028');
        $response->assertSee('2 year');
        $response->assertDontSee('June, 2027');
    }
}
