<?php

namespace Tests\Feature;

use App\Filament\Resources\InvoiceResource\Pages\EditInvoice;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VoidInvoiceFilamentTest extends TestCase
{
    use RefreshDatabase;

    private function makeInvoice(array $overrides = []): Invoice
    {
        $client = Client::create([
            'billing_name' => 'Filament Void Client',
            'nickname' => 'FVC',
        ]);

        return Invoice::create(array_merge([
            'invoice_no' => 'SR-00001/2026',
            'date' => '2026-05-01',
            'client_id' => $client->id,
        ], $overrides));
    }

    public function test_an_admin_can_void_an_invoice_from_the_edit_page_with_a_reason(): void
    {
        $user = User::factory()->create(['id' => 1]);
        $invoice = $this->makeInvoice();

        $this->actingAs($user);

        Livewire::test(EditInvoice::class, ['record' => $invoice->getRouteKey()])
            ->assertActionVisible('void')
            ->callAction('void', data: ['reason' => 'Billed to the wrong client'])
            ->assertHasNoActionErrors();

        $invoice->refresh();
        $this->assertSame(Invoice::STATUS_VOID, $invoice->status);
        $this->assertSame('Billed to the wrong client', $invoice->void_reason);
    }

    public function test_the_void_action_is_hidden_for_a_tax_invoice_from_an_earlier_day(): void
    {
        $user = User::factory()->create(['id' => 1]);
        $invoice = $this->makeInvoice([
            'invoice_no' => 'SI-00008/2026',
            'date' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($user);

        Livewire::test(EditInvoice::class, ['record' => $invoice->getRouteKey()])
            ->assertActionHidden('void');
    }

    public function test_the_void_action_is_hidden_once_the_invoice_is_void_or_paid(): void
    {
        $user = User::factory()->create(['id' => 1]);

        $voided = $this->makeInvoice(['invoice_no' => 'SR-00002/2026']);
        $voided->void('Duplicate');

        $paid = $this->makeInvoice(['invoice_no' => 'SI-00001/2026']);
        $paid->markAsPaid('2026-05-02');

        $this->actingAs($user);

        foreach ([$voided, $paid] as $invoice) {
            Livewire::test(EditInvoice::class, ['record' => $invoice->getRouteKey()])
                ->assertActionHidden('void');
        }
    }
}
