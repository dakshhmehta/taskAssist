<?php

namespace Tests\Unit;

use App\Jobs\GenerateInvoice;
use App\Mcp\Tools\GenerateAssetInvoice;
use App\Models\Client;
use App\Models\Email;
use App\Models\Hosting;
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
}
