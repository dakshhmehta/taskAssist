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

    public function test_it_creates_an_extra_only_invoice_with_current_year_serial_and_correct_totals(): void
    {
        $client = Client::create([
            'billing_name' => 'SIS (Test)',
            'nickname' => 'SIS',
        ]);

        $result = (new GenerateServiceInvoice)->handle([
            'client_id' => $client->id,
            'extras' => json_encode([
                [
                    'line_title' => 'SIS Website',
                    'price' => 69350,
                    'discount_value' => 3650,
                ],
            ]),
            'date' => now()->format('Y-m-d'),
        ]);

        $payload = json_decode($result->toArray()['content'][0]['text'], true);

        $this->assertSame('success', $payload['status'], $payload['message'] ?? 'no message');

        $invoice = Invoice::findOrFail($payload['invoice']['id']);

        $this->assertSame('SR-00001/' . now()->year, $invoice->invoice_no);
        $this->assertSame('SIS Website', $invoice->extras->first()->line_title);
        $this->assertEquals(69350, $invoice->total);
        $this->assertEquals(3650, $invoice->extras->first()->discount_value);
    }
}
