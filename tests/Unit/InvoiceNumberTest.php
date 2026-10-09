<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceNumberTest extends TestCase
{
    use RefreshDatabase;

    private function makeClient(): Client
    {
        return Client::create([
            'billing_name' => 'Number Test Client',
            'nickname' => 'NTC',
        ]);
    }

    public function test_it_uses_the_current_year_instead_of_a_hardcoded_year(): void
    {
        $number = Invoice::nextInvoiceNumber('SR-');

        $this->assertSame('SR-00001/' . now()->year, $number);
    }

    public function test_it_increments_from_the_highest_sequence_for_the_current_year(): void
    {
        $year = now()->year;
        $client = $this->makeClient();

        foreach ([2, 5, 3] as $sequence) {
            Invoice::create([
                'client_id' => $client->id,
                'date' => now(),
                'invoice_no' => 'SR-' . str_pad($sequence, 5, '0', STR_PAD_LEFT) . '/' . $year,
            ]);
        }

        $this->assertSame('SR-00006/' . $year, Invoice::nextInvoiceNumber('SR-'));
    }

    public function test_it_ignores_invoices_from_other_years(): void
    {
        Invoice::create([
            'client_id' => $this->makeClient()->id,
            'date' => now(),
            'invoice_no' => 'SR-00033/' . (now()->year - 1),
        ]);

        $this->assertSame('SR-00001/' . now()->year, Invoice::nextInvoiceNumber('SR-'));
    }

    public function test_it_ignores_invoices_with_a_different_prefix(): void
    {
        $year = now()->year;

        Invoice::create([
            'client_id' => $this->makeClient()->id,
            'date' => now(),
            'invoice_no' => 'DH-00014/' . $year,
        ]);

        $this->assertSame('SR-00001/' . $year, Invoice::nextInvoiceNumber('SR-'));
    }
}
