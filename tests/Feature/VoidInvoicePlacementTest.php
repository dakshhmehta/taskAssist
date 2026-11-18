<?php

namespace Tests\Feature;

use App\Filament\Resources\ClientResource\Widgets\ClientReceivablesTable;
use App\Filament\Resources\InvoiceResource\Pages\ListInvoices;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VoidInvoicePlacementTest extends TestCase
{
    use RefreshDatabase;

    private function makeClient(string $name = 'Void Placement Client'): Client
    {
        return Client::create([
            'billing_name' => $name,
            'nickname' => 'VPC',
        ]);
    }

    private function makeInvoice(?Client $client = null, array $overrides = []): Invoice
    {
        $client ??= $this->makeClient();

        return Invoice::create(array_merge([
            'invoice_no' => 'SR-00001/2026',
            'date' => '2026-05-01',
            'client_id' => $client->id,
        ], $overrides));
    }

    public function test_the_void_action_is_not_offered_in_the_invoice_table(): void
    {
        $user = User::factory()->create(['id' => 1]);
        $invoice = $this->makeInvoice();

        $this->actingAs($user);

        Livewire::test(ListInvoices::class)
            ->assertTableActionDoesNotExist('void');
    }

    public function test_a_client_whose_only_unpaid_invoice_is_void_is_not_shown_as_receivable(): void
    {
        $user = User::factory()->create(['id' => 1]);
        $this->actingAs($user);

        $client = $this->makeClient();
        $invoice = $this->makeInvoice($client);
        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'itemable_type' => \App\Models\Domain::class,
            'itemable_id' => 1,
            'price' => 1500,
        ]);

        $invoice->void('Billed to the wrong client');

        Livewire::test(ClientReceivablesTable::class)
            ->assertCanNotSeeTableRecords([$client]);

        $this->assertSame(0.0, (float) $client->fresh()->getReceivable());
    }

    public function test_a_client_with_an_open_invoice_is_still_shown_as_receivable(): void
    {
        $user = User::factory()->create(['id' => 1]);
        $this->actingAs($user);

        $client = $this->makeClient();
        $voided = $this->makeInvoice($client);
        $voided->void('Duplicate');

        $open = $this->makeInvoice($client, ['invoice_no' => 'SR-00002/2026']);
        InvoiceItem::create([
            'invoice_id' => $open->id,
            'itemable_type' => \App\Models\Domain::class,
            'itemable_id' => 1,
            'price' => 2500,
        ]);

        Livewire::test(ClientReceivablesTable::class)
            ->assertCanSeeTableRecords([$client]);

        $this->assertSame(2500.0, (float) $client->fresh()->getReceivable());
    }

    public function test_a_voided_proforma_is_marked_void_on_the_print_view(): void
    {
        $invoice = $this->makeInvoice();
        $invoice->void('Client cancelled the order');

        $response = $this->get(route('invoices.print', [$invoice->id, 'view' => 1]));

        $response->assertOk();
        $response->assertSee('PROFORMA INVOICE');
        $response->assertSee('class="void-type">VOID', false);
    }

    public function test_an_open_proforma_does_not_carry_the_void_marker(): void
    {
        $invoice = $this->makeInvoice();

        $response = $this->get(route('invoices.print', [$invoice->id, 'view' => 1]));

        $response->assertOk();
        $response->assertDontSee('class="void-type">VOID', false);
    }
}
