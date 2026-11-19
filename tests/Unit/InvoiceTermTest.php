<?php

namespace Tests\Unit;

use App\Mcp\Tools\GenerateAssetInvoice;
use App\Mcp\Tools\GenerateServiceInvoice;
use App\Mcp\Tools\UpdateInvoice;
use App\Models\Client;
use App\Models\Domain;
use App\Models\Email;
use App\Models\Hosting;
use App\Models\HostingPackage;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Mcp\Request;
use Tests\TestCase;

class InvoiceTermTest extends TestCase
{
    use RefreshDatabase;

    private function client(): Client
    {
        return Client::create(['billing_name' => 'Term Client', 'nickname' => 'TERM']);
    }

    public function test_asset_invoice_shifts_domain_expiry_and_reprices_from_latest_config(): void
    {
        $client = $this->client();
        Domain::create([
            'tld' => 'termdomain.com',
            'client_id' => $client->id,
            'expiry_date' => '2027-06-15',
        ]);

        $result = (new GenerateAssetInvoice)->handle(new Request([
            'domain' => 'termdomain.com',
            'type' => 'domain',
            'invoice_date' => '2026-06-15',
            'term' => 1,
        ]));

        $payload = json_decode((string) $result->content(), true);
        $this->assertSame('success', $payload['status'], $payload['message'] ?? '');

        $item = Invoice::findOrFail($payload['invoice']['id'])->items()->firstOrFail();

        $this->assertSame('2028-06-15', $item->expiry_date->format('Y-m-d'));
        $this->assertEquals(config('pricing.domains.com') * 2, $item->price);
    }

    public function test_asset_invoice_multiplies_hosting_price_by_term(): void
    {
        $client = $this->client();
        $package = HostingPackage::create(['storage' => 1000, 'price' => 1500]);

        Hosting::create([
            'domain' => 'termhosting.com',
            'client_id' => $client->id,
            'package_id' => $package->id,
            'expiry_date' => '2027-06-15',
        ]);

        $result = (new GenerateAssetInvoice)->handle(new Request([
            'domain' => 'termhosting.com',
            'type' => 'hosting',
            'invoice_date' => '2026-06-15',
            'term' => 2,
        ]));

        $payload = json_decode((string) $result->content(), true);
        $this->assertSame('success', $payload['status'], $payload['message'] ?? '');

        $item = Invoice::findOrFail($payload['invoice']['id'])->items()->firstOrFail();

        $this->assertSame('2029-06-15', $item->expiry_date->format('Y-m-d'));
        $this->assertEquals(3000, $item->price);
    }

    public function test_asset_cart_shifts_domain_and_hosting_but_leaves_email_and_extras_alone(): void
    {
        $client = $this->client();
        $package = HostingPackage::create(['storage' => 1000, 'price' => 1500]);

        $domain = Domain::create([
            'tld' => 'cartterm.com',
            'client_id' => $client->id,
            'expiry_date' => '2027-06-15',
        ]);
        $hosting = Hosting::create([
            'domain' => 'cartterm.com',
            'client_id' => $client->id,
            'package_id' => $package->id,
            'expiry_date' => '2027-06-15',
        ]);
        $email = Email::create([
            'domain' => 'cartterm.com',
            'provider' => 'google',
            'client_id' => $client->id,
            'expiry_date' => '2027-06-15',
        ]);

        $result = (new GenerateAssetInvoice)->handle(new Request([
            'cart' => json_encode([
                ['type' => 'domain', 'domain' => 'cartterm.com'],
                ['type' => 'hosting', 'domain' => 'cartterm.com'],
                ['type' => 'email', 'domain' => 'cartterm.com'],
                ['type' => 'extra', 'line_title' => 'Setup fee', 'price' => 500],
            ]),
            'invoice_date' => '2026-06-15',
            'term' => 1,
        ]));

        $payload = json_decode((string) $result->content(), true);
        $this->assertSame('success', $payload['status'], $payload['message'] ?? '');

        $invoice = Invoice::findOrFail($payload['invoice']['id']);

        $this->assertSame('2028-06-15', $invoice->items->first(fn ($item) => $item->itemable_type === Domain::class && $item->itemable_id === $domain->id)->expiry_date->format('Y-m-d'));
        $this->assertSame('2028-06-15', $invoice->items->first(fn ($item) => $item->itemable_type === Hosting::class && $item->itemable_id === $hosting->id)->expiry_date->format('Y-m-d'));
        $this->assertSame('2027-06-15', $invoice->items->first(fn ($item) => $item->itemable_type === Email::class && $item->itemable_id === $email->id)->expiry_date->format('Y-m-d'));
        $this->assertEquals(500, $invoice->extras->first()->price);
    }

    public function test_service_invoice_shifts_expiry_and_reprices(): void
    {
        $client = $this->client();
        $domain = Domain::create([
            'tld' => 'serviceterm.com',
            'client_id' => $client->id,
            'expiry_date' => '2027-06-15',
        ]);

        $result = (new GenerateServiceInvoice)->handle(new Request([
            'client_id' => $client->id,
            'items' => json_encode([
                ['itemable_type' => 'domain', 'itemable_id' => $domain->id],
            ]),
            'date' => '2026-06-15',
            'term' => 1,
        ]));

        $payload = json_decode((string) $result->content(), true);
        $this->assertSame('success', $payload['status'], $payload['message'] ?? '');

        $item = Invoice::findOrFail($payload['invoice']['id'])->items()->firstOrFail();

        $this->assertSame('2028-06-15', $item->expiry_date->format('Y-m-d'));
        $this->assertEquals(config('pricing.domains.com') * 2, $item->price);
    }

    public function test_service_invoice_explicit_expiry_date_wins_over_term(): void
    {
        $client = $this->client();
        $domain = Domain::create([
            'tld' => 'explicitterm.com',
            'client_id' => $client->id,
            'expiry_date' => '2027-06-15',
        ]);

        $result = (new GenerateServiceInvoice)->handle(new Request([
            'client_id' => $client->id,
            'items' => json_encode([
                ['itemable_type' => 'domain', 'itemable_id' => $domain->id, 'expiry_date' => '2027-01-01'],
            ]),
            'date' => '2026-06-15',
            'term' => 1,
        ]));

        $payload = json_decode((string) $result->content(), true);
        $this->assertSame('success', $payload['status'], $payload['message'] ?? '');

        $item = Invoice::findOrFail($payload['invoice']['id'])->items()->firstOrFail();

        $this->assertSame('2027-01-01', $item->expiry_date->format('Y-m-d'));
    }

    public function test_update_invoice_shifts_domain_and_hosting_expiry_without_repricing(): void
    {
        $client = $this->client();
        $package = HostingPackage::create(['storage' => 1000, 'price' => 1500]);

        $domain = Domain::create([
            'tld' => 'updateterm.com',
            'client_id' => $client->id,
            'expiry_date' => '2027-06-15',
        ]);
        $hosting = Hosting::create([
            'domain' => 'updateterm.com',
            'client_id' => $client->id,
            'package_id' => $package->id,
            'expiry_date' => '2027-06-15',
        ]);
        $email = Email::create([
            'domain' => 'updateterm.com',
            'provider' => 'google',
            'client_id' => $client->id,
            'expiry_date' => '2027-06-15',
        ]);

        $invoice = Invoice::create([
            'invoice_no' => 'DH-TERM-1',
            'date' => '2026-06-15',
            'client_id' => $client->id,
        ]);

        $domainItem = InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'itemable_type' => Domain::class,
            'itemable_id' => $domain->id,
            'price' => 1000,
            'expiry_date' => '2027-06-15',
        ]);
        $hostingItem = InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'itemable_type' => Hosting::class,
            'itemable_id' => $hosting->id,
            'price' => 1500,
            'expiry_date' => '2027-06-15',
        ]);
        $emailItem = InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'itemable_type' => Email::class,
            'itemable_id' => $email->id,
            'price' => 3270,
            'expiry_date' => '2027-06-15',
        ]);

        $result = (new UpdateInvoice)->handle(new Request([
            'invoice_id' => $invoice->id,
            'term' => 1,
        ]));

        $payload = json_decode((string) $result->content(), true);
        $this->assertSame('success', $payload['status'], $payload['message'] ?? '');

        $domainItem->refresh();
        $hostingItem->refresh();
        $emailItem->refresh();

        $this->assertSame('2028-06-15', $domainItem->expiry_date->format('Y-m-d'));
        $this->assertSame('2028-06-15', $hostingItem->expiry_date->format('Y-m-d'));
        $this->assertSame('2027-06-15', $emailItem->expiry_date->format('Y-m-d'));
        $this->assertEquals(1000, $domainItem->price);
        $this->assertEquals(1500, $hostingItem->price);
    }

    public function test_update_invoice_leaves_explicit_expiry_date_alone_when_term_given(): void
    {
        $client = $this->client();
        $domain = Domain::create([
            'tld' => 'updateexplicit.com',
            'client_id' => $client->id,
            'expiry_date' => '2027-06-15',
        ]);

        $invoice = Invoice::create([
            'invoice_no' => 'DH-TERM-2',
            'date' => '2026-06-15',
            'client_id' => $client->id,
        ]);

        $item = InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'itemable_type' => Domain::class,
            'itemable_id' => $domain->id,
            'price' => 1000,
            'expiry_date' => '2027-06-15',
        ]);

        (new UpdateInvoice)->handle(new Request([
            'invoice_id' => $invoice->id,
            'term' => 1,
            'items' => [
                ['id' => $item->id, 'expiry_date' => '2027-01-01'],
            ],
        ]));

        $this->assertSame('2027-01-01', $item->refresh()->expiry_date->format('Y-m-d'));
    }

    public function test_update_invoice_rolls_back_every_write_when_one_item_fails(): void
    {
        $client = $this->client();
        $domain = Domain::create([
            'tld' => 'rollbackterm.com',
            'client_id' => $client->id,
            'expiry_date' => '2027-06-15',
        ]);

        $invoice = Invoice::create([
            'invoice_no' => 'DH-TERM-3',
            'date' => '2026-06-15',
            'client_id' => $client->id,
        ]);

        $first = InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'itemable_type' => Domain::class,
            'itemable_id' => $domain->id,
            'price' => 1000,
            'expiry_date' => '2027-06-15',
        ]);
        $second = InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'itemable_type' => Domain::class,
            'itemable_id' => $domain->id,
            'price' => 1000,
            'expiry_date' => '2027-06-15',
        ]);

        try {
            (new UpdateInvoice)->handle(new Request([
                'invoice_id' => $invoice->id,
                'status' => Invoice::STATUS_CLOSED,
                'items' => [
                    ['id' => $first->id, 'price' => 1500],
                    ['id' => $second->id, 'expiry_date' => 'not-a-date'],
                ],
            ]));

            $this->fail('An invalid expiry_date should have thrown.');
        } catch (\Throwable $e) {
            // Expected: the second item fails, so the transaction rolls back.
        }

        $this->assertSame(Invoice::STATUS_OPEN, $invoice->refresh()->status);
        $this->assertEquals(1000, $first->refresh()->price);
    }

    public function test_negative_term_is_rejected_without_creating_an_invoice(): void
    {
        $client = $this->client();
        Domain::create([
            'tld' => 'negativeterm.com',
            'client_id' => $client->id,
            'expiry_date' => '2027-06-15',
        ]);

        $result = (new GenerateAssetInvoice)->handle(new Request([
            'domain' => 'negativeterm.com',
            'type' => 'domain',
            'term' => -1,
        ]));

        $payload = json_decode((string) $result->content(), true);

        $this->assertSame('error', $payload['status']);
        $this->assertSame(0, Invoice::count());
    }
}
