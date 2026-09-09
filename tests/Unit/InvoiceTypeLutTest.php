<?php

namespace Tests\Unit;

use App\Mcp\Tools\GenerateAssetInvoice;
use App\Mcp\Tools\GetInvoice;
use App\Mcp\Tools\MarkInvoiceAsPaid;
use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceTypeLutTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_invoice_exposes_type_and_lut_no(): void
    {
        $client = Client::create(['billing_name' => 'LUT Client', 'nickname' => 'LC']);
        $invoice = Invoice::create([
            'invoice_no' => 'SI-00001/2025',
            'date' => '2025-05-01',
            'client_id' => $client->id,
        ]);

        $result = (new GetInvoice)->handle(['invoice_id' => $invoice->id]);
        $payload = json_decode($result->toArray()['content'][0]['text'], true);

        $this->assertSame('success', $payload['status']);
        $this->assertSame('TAX', $payload['invoice']['type']);
        $this->assertSame(config('app.gstin_lut_no'), $payload['invoice']['lut_no']);
    }

    public function test_mark_invoice_as_paid_exposes_type_and_lut_no(): void
    {
        $client = Client::create(['billing_name' => 'Paid Client', 'nickname' => 'PC']);
        $invoice = Invoice::create([
            'invoice_no' => 'SI-00002/2025',
            'date' => '2025-05-01',
            'client_id' => $client->id,
        ]);

        $result = (new MarkInvoiceAsPaid)->handle(['invoice_id' => $invoice->id]);
        $payload = json_decode($result->toArray()['content'][0]['text'], true);

        $this->assertSame('success', $payload['status']);
        $this->assertSame('TAX', $payload['invoice']['type']);
        $this->assertSame(config('app.gstin_lut_no'), $payload['invoice']['lut_no']);
    }

    public function test_asset_invoice_summary_exposes_type_and_lut_no(): void
    {
        $client = Client::create(['billing_name' => 'Extras Client', 'nickname' => 'EC']);

        $result = (new GenerateAssetInvoice)->handle([
            'cart' => json_encode([
                ['type' => 'extra', 'line_title' => 'Consulting', 'price' => 1000],
            ]),
            'client_id' => $client->id,
        ]);
        $payload = json_decode($result->toArray()['content'][0]['text'], true);

        $this->assertSame('success', $payload['status']);
        $this->assertSame('PROFORMA', $payload['invoice']['type']);
        $this->assertSame(config('app.gstin_lut_no'), $payload['invoice']['lut_no']);
    }
}
