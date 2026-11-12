<?php

namespace Tests\Unit;

use App\Mcp\Tools\ConvertToTaxInvoice;
use App\Mcp\Tools\MarkInvoiceAsPaid;
use App\Mcp\Tools\VoidInvoice;
use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Mcp\Request;
use Tests\TestCase;

class VoidInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeInvoice(array $overrides = []): Invoice
    {
        $client = Client::create([
            'billing_name' => 'Void Client',
            'nickname' => 'VC',
        ]);

        return Invoice::create(array_merge([
            'invoice_no' => 'SR-00001/2026',
            'date' => '2026-05-01',
            'client_id' => $client->id,
        ], $overrides));
    }

    private function payload($result): array
    {
        return json_decode((string) $result->content(), true);
    }

    public function test_it_voids_an_invoice_and_stores_the_reason(): void
    {
        $invoice = $this->makeInvoice();

        $payload = $this->payload((new VoidInvoice)->handle(new Request([
            'invoice_id' => $invoice->id,
            'reason' => 'Wrong pricing, reissued as SR-00002/2026',
        ])));

        $this->assertSame('success', $payload['status'], $payload['message'] ?? 'no message');
        $this->assertSame(Invoice::STATUS_VOID, $payload['invoice']['status']);

        $invoice->refresh();
        $this->assertTrue($invoice->isVoid());
        $this->assertSame(Invoice::STATUS_VOID, $invoice->status);
        $this->assertSame('Wrong pricing, reissued as SR-00002/2026', $invoice->void_reason);
        $this->assertNotNull($invoice->voided_at);
    }

    public function test_it_requires_a_reason_to_void(): void
    {
        $invoice = $this->makeInvoice();

        $payload = $this->payload((new VoidInvoice)->handle(new Request([
            'invoice_id' => $invoice->id,
            'reason' => '   ',
        ])));

        $this->assertSame('error', $payload['status']);
        $this->assertSame(Invoice::STATUS_OPEN, $invoice->refresh()->status);
    }

    public function test_a_void_invoice_is_not_counted_as_receivable(): void
    {
        $invoice = $this->makeInvoice();
        $invoice->extras()->create(['line_title' => 'Website', 'line_description' => 'Website work', 'price' => 1000]);

        $this->assertEquals(1000, $invoice->client->getReceivable());

        $invoice->void('Client cancelled the project');

        $this->assertEquals(0, $invoice->client->fresh()->getReceivable());
        $this->assertFalse(Invoice::unpaid()->whereKey($invoice->id)->exists());
        $this->assertTrue(Invoice::voided()->whereKey($invoice->id)->exists());
    }

    public function test_it_rejects_voiding_twice(): void
    {
        $invoice = $this->makeInvoice();
        $invoice->void('Duplicate invoice');

        $payload = $this->payload((new VoidInvoice)->handle(new Request([
            'invoice_id' => $invoice->id,
            'reason' => 'Trying again',
        ])));

        $this->assertSame('error', $payload['status']);
        $this->assertStringContainsString('already void', $payload['message']);
        $this->assertSame('Duplicate invoice', $invoice->refresh()->void_reason);
    }

    public function test_it_rejects_voiding_a_paid_invoice(): void
    {
        $invoice = $this->makeInvoice(['invoice_no' => 'SI-00001/2026']);
        $invoice->markAsPaid('2026-05-02');

        $payload = $this->payload((new VoidInvoice)->handle(new Request([
            'invoice_id' => $invoice->id,
            'reason' => 'Refund requested',
        ])));

        $this->assertSame('error', $payload['status']);
        $this->assertStringContainsString('paid', $payload['message']);
        $this->assertSame(Invoice::STATUS_OPEN, $invoice->refresh()->status);
    }

    public function test_it_rejects_voiding_a_proforma_that_has_a_tax_invoice(): void
    {
        $invoice = $this->makeInvoice();
        $invoice->createTaxInvoice();

        $payload = $this->payload((new VoidInvoice)->handle(new Request([
            'invoice_id' => $invoice->id,
            'reason' => 'Cancelled',
        ])));

        $this->assertSame('error', $payload['status']);
        $this->assertStringContainsString('tax invoice', $payload['message']);
        $this->assertSame(Invoice::STATUS_OPEN, $invoice->refresh()->status);
    }

    public function test_a_void_invoice_cannot_be_marked_paid_or_converted(): void
    {
        $invoice = $this->makeInvoice();
        $invoice->void('Cancelled');

        $paid = $this->payload((new MarkInvoiceAsPaid)->handle(new Request([
            'invoice_id' => $invoice->id,
        ])));

        $this->assertSame('error', $paid['status']);
        $this->assertStringContainsString('void', $paid['message']);

        $converted = $this->payload((new ConvertToTaxInvoice)->handle(new Request([
            'invoice_id' => $invoice->id,
        ])));

        $this->assertSame('error', $converted['status']);
        $this->assertStringContainsString('void', $converted['message']);

        $this->assertNull($invoice->refresh()->paid_date);
        $this->assertFalse($invoice->hasTaxInvoice());
    }

    public function test_the_model_guards_voiding_and_payment_directly(): void
    {
        $invoice = $this->makeInvoice();

        try {
            $invoice->void('  ');
            $this->fail('Voiding without a reason should throw.');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame(Invoice::STATUS_OPEN, $invoice->refresh()->status);
        }

        $invoice->void('Cancelled');
        $this->expectException(\Exception::class);
        $invoice->markAsPaid('2026-05-02');
    }
}
