<?php

namespace Tests\Unit;

use App\Mail\InvoiceGenerated;
use App\Models\Domain;
use App\Models\Invoice;
use Tests\TestCase;

class InvoiceGeneratedMailTest extends TestCase
{
    public function test_item_invoice_subject_includes_invoice_number_item_and_financial_year(): void
    {
        $invoice = new Invoice([
            'invoice_no' => 'DH-00042/2026',
            'date' => '2026-04-01',
        ]);
        $item = new Domain(['tld' => 'example.com']);

        $subject = (new InvoiceGenerated($invoice, $item))->envelope()->subject;

        $this->assertSame(
            'Invoice No. DH-00042/2026 - example.com - 2026-27',
            $subject
        );
    }

    public function test_extra_only_invoice_subject_uses_the_financial_year_from_the_invoice_date(): void
    {
        $invoice = new Invoice([
            'invoice_no' => 'DH-00041/2025',
            'date' => '2026-03-31',
        ]);

        $subject = (new InvoiceGenerated($invoice, null, 'Website Maintenance'))->envelope()->subject;

        $this->assertSame(
            'Invoice No. DH-00041/2025 - Website Maintenance - 2025-26',
            $subject
        );
    }
}
