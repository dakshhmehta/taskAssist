<?php

namespace App\Mcp\Tools;

use App\Jobs\EmailInvoice as EmailInvoiceJob;
use App\Models\Invoice;
use Generator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\Title;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;

#[Title('Send Invoice Email')]
class SendInvoiceEmail extends Tool
{
    public function description(): string
    {
        return 'Send an invoice email to the client by invoice ID or invoice number.';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        return $schema->integer('invoice_id', 'The ID of the invoice to email.')
            ->string('invoice_no', 'The invoice number to look up.');
    }

    public function handle(array $arguments): ToolResult|Generator
    {
        $invoiceId = $arguments['invoice_id'] ?? null;
        $invoiceNo = $arguments['invoice_no'] ?? null;

        if (! $invoiceId && ! $invoiceNo) {
            return ToolResult::json(['status' => 'error', 'message' => 'Either invoice_id or invoice_no must be provided.']);
        }

        $invoice = Invoice::query()
            ->when($invoiceId, fn($q) => $q->where('id', $invoiceId))
            ->when(! $invoiceId && $invoiceNo, fn($q) => $q->where('invoice_no', $invoiceNo))
            ->with(['items.itemable', 'client'])
            ->first();

        if (! $invoice) {
            return ToolResult::json(['status' => 'error', 'message' => 'Invoice not found.']);
        }

        $firstItem = $invoice->items->first()?->itemable;

        if (! $firstItem) {
            return ToolResult::json(['status' => 'error', 'message' => 'Invoice has no items (domain/hosting/email). Cannot determine email subject.']);
        }

        EmailInvoiceJob::dispatch($invoice, $firstItem);

        return ToolResult::json([
            'status' => 'success',
            'message' => "Invoice email for '{$invoice->invoice_no}' has been queued for sending to " . ($invoice->client->email ?? 'default recipient') . ".",
        ]);
    }
}
