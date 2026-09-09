<?php

namespace Tests\Unit;

use App\Jobs\GenerateInvoice;
use App\Mcp\Tools\GenerateAssetInvoice;
use App\Models\Client;
use App\Models\Domain;
use App\Models\Email;
use App\Models\Hosting;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class GenerateAssetInvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_dispatches_invoice_for_hosting_type(): void
    {
        Bus::fake();

        $client = Client::create(['billing_name' => 'Test Client', 'nickname' => 'TC']);
        $hosting = Hosting::create([
            'domain' => 'hostingtest.com',
            'client_id' => $client->id,
            'expiry_date' => now()->addYear(),
        ]);

        $tool = new GenerateAssetInvoice;
        $result = $tool->handle([
            'domain' => 'hostingtest.com',
            'type' => 'hosting',
        ]);

        Bus::assertDispatched(GenerateInvoice::class, function (GenerateInvoice $job) use ($hosting) {
            return count($job->items) === 1
                && $job->items[0]->id === $hosting->id
                && $job->items[0] instanceof Hosting;
        });

        $this->assertNotNull($result);
    }

    public function test_it_dispatches_invoice_for_email_type(): void
    {
        Bus::fake();

        $client = Client::create(['billing_name' => 'Test Client', 'nickname' => 'TC']);
        $email = Email::create([
            'domain' => 'emailtest.com',
            'provider' => 'google',
            'client_id' => $client->id,
            'expiry_date' => now()->addYear(),
        ]);

        $tool = new GenerateAssetInvoice;
        $tool->handle([
            'domain' => 'emailtest.com',
            'type' => 'email',
        ]);

        Bus::assertDispatched(GenerateInvoice::class, function (GenerateInvoice $job) use ($email) {
            return count($job->items) === 1
                && $job->items[0]->id === $email->id
                && $job->items[0] instanceof Email;
        });
    }

    public function test_it_creates_one_dh_invoice_from_a_cart_with_assets_extra_discount_and_footnote(): void
    {
        $client = Client::create(['billing_name' => 'Cart Client', 'nickname' => 'CC']);
        $domain = Domain::create([
            'tld' => 'cartdomain.com',
            'client_id' => $client->id,
            'expiry_date' => now()->addYear(),
        ]);
        Hosting::create([
            'domain' => 'carthost.com',
            'client_id' => $client->id,
            'expiry_date' => now()->addYear(),
        ]);
        Email::create([
            'domain' => 'cartmail.com',
            'provider' => 'google',
            'client_id' => $client->id,
            'expiry_date' => now()->addYear(),
        ]);

        $tool = new GenerateAssetInvoice;
        $result = $tool->handle([
            'cart' => json_encode([
                ['type' => 'domain', 'domain' => 'cartdomain.com', 'discount_value' => 100],
                ['type' => 'hosting', 'domain' => 'carthost.com'],
                ['type' => 'email', 'domain' => 'cartmail.com'],
                ['type' => 'extra', 'line_title' => 'Setup fee', 'price' => 500, 'discount_value' => 50],
            ]),
            'footnote' => 'Pay within 7 days.',
        ]);

        $payload = json_decode($result->toArray()['content'][0]['text'], true);

        $this->assertSame('success', $payload['status'], $payload['message'] ?? 'No message in payload.');
        $this->assertStringStartsWith('DH-', $payload['invoice']['invoice_no']);

        $invoice = Invoice::where('invoice_no', $payload['invoice']['invoice_no'])->firstOrFail();

        $this->assertCount(3, $invoice->items);
        $this->assertCount(1, $invoice->extras);
        $this->assertSame('Pay within 7 days.', $invoice->footnote);
        $this->assertEquals(100, $invoice->items->firstWhere('itemable_id', $domain->id)->discount_value);
        $this->assertSame('Setup fee', $invoice->extras->first()->line_title);
        $this->assertEquals(50, $invoice->extras->first()->discount_value);
    }
}
