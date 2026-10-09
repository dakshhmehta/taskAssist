<?php

namespace Tests\Unit;

use App\Mcp\Tools\GenerateServiceInvoice;
use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateServiceInvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_extra_only_invoice_with_financial_year_serial_and_correct_totals(): void
    {
        $client = Client::create([
            'billing_name' => 'SIS (Test)',
            'nickname' => 'SIS',
        ]);

        // February 2026 belongs to the 2025-26 financial year.
        $result = (new GenerateServiceInvoice)->handle([
            'client_id' => $client->id,
            'extras' => json_encode([
                [
                    'line_title' => 'SIS Website',
                    'price' => 69350,
                    'discount_value' => 3650,
                ],
            ]),
            'date' => '2026-02-15',
        ]);

        $payload = json_decode($result->toArray()['content'][0]['text'], true);

        $this->assertSame('success', $payload['status'], $payload['message'] ?? 'no message');

        $invoice = Invoice::findOrFail($payload['invoice']['id']);

        $this->assertSame('SR-00001/2025', $invoice->invoice_no);
        $this->assertSame('2026-02-15', $invoice->date->format('Y-m-d'));
        $this->assertSame('SIS Website', $invoice->extras->first()->line_title);
        $this->assertEquals(69350, $invoice->total);
        $this->assertEquals(3650, $invoice->extras->first()->discount_value);
    }
}
