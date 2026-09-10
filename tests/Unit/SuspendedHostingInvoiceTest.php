<?php

namespace Tests\Unit;

use App\Jobs\GenerateInvoice as GenerateInvoiceJob;
use App\Mcp\Tools\GenerateAssetInvoice;
use App\Mcp\Tools\GenerateServiceInvoice;
use App\Models\Client;
use App\Models\Domain;
use App\Models\Hosting;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuspendedHostingInvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_invoice_rejects_suspended_hosting(): void
    {
        $client = Client::create(['billing_name' => 'Suspended Client', 'nickname' => 'SC']);
        $hosting = Hosting::create([
            'domain' => 'suspended.com',
            'client_id' => $client->id,
            'expiry_date' => now()->addYear(),
            'suspended_at' => now(),
        ]);

        $result = (new GenerateServiceInvoice)->handle([
            'client_id' => $client->id,
            'items' => json_encode([
                ['itemable_type' => 'hosting', 'itemable_id' => $hosting->id],
            ]),
        ]);
        $payload = json_decode($result->toArray()['content'][0]['text'], true);

        $this->assertSame('error', $payload['status']);
        $this->assertStringContainsStringIgnoringCase('suspended', $payload['message']);
        $this->assertSame(0, Invoice::count());
    }

    public function test_asset_invoice_rejects_terminated_hosting(): void
    {
        $client = Client::create(['billing_name' => 'Terminated Client', 'nickname' => 'TC']);
        Hosting::create([
            'domain' => 'terminated.com',
            'client_id' => $client->id,
            'expiry_date' => now()->addYear(),
            'terminated_at' => now(),
        ]);

        $result = (new GenerateAssetInvoice)->handle([
            'domain' => 'terminated.com',
            'type' => 'hosting',
        ]);
        $payload = json_decode($result->toArray()['content'][0]['text'], true);

        $this->assertSame('error', $payload['status']);
        $this->assertStringContainsStringIgnoringCase('terminated', $payload['message']);
        $this->assertSame(0, Invoice::count());
    }

    public function test_bulk_job_skips_suspended_hosting_but_invoices_the_domain(): void
    {
        $client = Client::create(['billing_name' => 'Bulk Client', 'nickname' => 'BC']);
        $domain = Domain::create([
            'tld' => 'bulkdomain.com',
            'client_id' => $client->id,
            'expiry_date' => now()->addYear(),
        ]);
        $suspendedHosting = Hosting::create([
            'domain' => 'bulkdomain.com',
            'client_id' => $client->id,
            'expiry_date' => now()->addYear(),
            'suspended_at' => now(),
        ]);

        GenerateInvoiceJob::dispatchSync([$domain, $suspendedHosting]);

        $invoice = Invoice::firstOrFail();

        $this->assertCount(1, $invoice->items);
        $this->assertSame(Domain::class, $invoice->items->first()->itemable_type);
    }
}
