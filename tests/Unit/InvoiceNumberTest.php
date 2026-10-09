<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\Invoice;
use Carbon\Carbon;
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

    public function test_it_uses_the_financial_year_start_year_for_dates_from_april_onwards(): void
    {
        $this->assertSame('SR-00001/2026', Invoice::nextInvoiceNumber('SR-', '2026-04-01'));
        $this->assertSame('SR-00001/2026', Invoice::nextInvoiceNumber('SR-', '2026-10-09'));
        $this->assertSame('SR-00001/2026', Invoice::nextInvoiceNumber('SR-', '2027-03-31'));
    }

    public function test_it_uses_the_previous_year_for_dates_before_april(): void
    {
        $this->assertSame('SR-00001/2025', Invoice::nextInvoiceNumber('SR-', '2025-12-31'));
        $this->assertSame('SR-00001/2025', Invoice::nextInvoiceNumber('SR-', '2026-01-01'));
        $this->assertSame('SR-00001/2025', Invoice::nextInvoiceNumber('SR-', '2026-03-31'));
    }

    public function test_it_defaults_to_the_current_financial_year_when_no_date_is_given(): void
    {
        Carbon::setTestNow('2027-02-15'); // FY 2026-27

        $this->assertSame('SR-00001/2026', Invoice::nextInvoiceNumber('SR-'));

        Carbon::setTestNow();
    }

    public function test_it_increments_from_the_highest_sequence_for_the_financial_year(): void
    {
        $client = $this->makeClient();

        foreach ([2, 5, 3] as $sequence) {
            Invoice::create([
                'client_id' => $client->id,
                'date' => '2026-05-01',
                'invoice_no' => 'SR-' . str_pad($sequence, 5, '0', STR_PAD_LEFT) . '/2026',
            ]);
        }

        $this->assertSame('SR-00006/2026', Invoice::nextInvoiceNumber('SR-', '2026-10-09'));
    }

    public function test_it_ignores_invoices_from_other_financial_years(): void
    {
        Invoice::create([
            'client_id' => $this->makeClient()->id,
            'date' => '2025-01-01',
            'invoice_no' => 'SR-00033/2024',
        ]);

        $this->assertSame('SR-00001/2025', Invoice::nextInvoiceNumber('SR-', '2026-03-31'));
    }

    public function test_it_ignores_invoices_with_a_different_prefix(): void
    {
        Invoice::create([
            'client_id' => $this->makeClient()->id,
            'date' => '2026-05-01',
            'invoice_no' => 'DH-00014/2026',
        ]);

        $this->assertSame('SR-00001/2026', Invoice::nextInvoiceNumber('SR-', '2026-10-09'));
    }
}
