<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\Domain;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainRenewalTest extends TestCase
{
    use RefreshDatabase;

    public function test_domain_is_due_for_renewal_with_specific_dates()
    {
        // Set "now" to 2026-05-12
        Carbon::setTestNow(Carbon::parse('2026-05-12'));

        // Create a client
        $client = Client::create([
            'billing_name' => 'Test Client',
            'nickname' => 'Test',
        ]);

        // Create a domain
        $domain = Domain::create([
            'tld' => 'example.com',
            'expiry_date' => Carbon::parse('2027-05-11'),
            'client_id' => $client->id,
        ]);

        // Create an invoice for this domain with date 2025-06-10
        $invoice = Invoice::create([
            'client_id' => $client->id,
            'date' => Carbon::parse('2025-06-10'),
            'invoice_no' => 'INV-001',
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'itemable_type' => Domain::class,
            'itemable_id' => $domain->id,
            'price' => 100,
        ]);

        // Ensure last_invoiced_date is correctly picked up
        $this->assertEquals('2025-06-10', $domain->last_invoiced_date->format('Y-m-d'));

        // The test case: should be due for renewal
        // Expiry: 2027-05-11
        // Last Invoice: 2025-06-10
        // Now: 2026-05-12
        $this->assertTrue($domain->dueForRenewal(), 'Domain should be due for renewal but it returned false.');
    }

    public function test_domain_is_not_due_for_renewal_if_invoiced_in_advance()
    {
        // Set "now" to 2026-05-12
        Carbon::setTestNow(Carbon::parse('2026-05-12'));

        $client = Client::create([
            'billing_name' => 'Test Client',
            'nickname' => 'Test',
        ]);

        $domain = Domain::create([
            'tld' => 'example.com',
            'expiry_date' => Carbon::parse('2027-04-07'),
            'client_id' => $client->id,
        ]);

        // Invoiced in advance on 2026-01-05
        $invoice = Invoice::create([
            'client_id' => $client->id,
            'date' => Carbon::parse('2026-01-05'),
            'invoice_no' => 'INV-002',
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'itemable_type' => Domain::class,
            'itemable_id' => $domain->id,
            'price' => 100,
        ]);

        // The test case: should NOT be due for renewal
        $this->assertFalse($domain->dueForRenewal(), 'Domain was invoiced in advance but it returned true.');
    }
}
