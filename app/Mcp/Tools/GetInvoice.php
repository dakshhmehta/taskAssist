<?php

namespace App\Mcp\Tools;

use App\Models\Invoice;
use Generator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\Title;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;

#[Title('Get Invoice')]
class GetInvoice extends Tool
{
    public function description(): string
    {
        return 'Get full invoice details by invoice ID or invoice number. Returns client, account, items, and financial totals.';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        return $schema->integer('invoice_id', 'The ID of the invoice to retrieve.')
            ->string('invoice_no', 'The invoice number to look up (e.g., "DH-00123/2025").');
    }

    public function handle(array $arguments): ToolResult|Generator
    {
        $invoice = Invoice::query()
            ->with(['client.account', 'items.itemable', 'extras', 'taxInvoice', 'proformaInvoice'])
            ->when(isset($arguments['invoice_id']), fn($q) => $q->where('id', $arguments['invoice_id']))
            ->when(! isset($arguments['invoice_id']) && isset($arguments['invoice_no']), fn($q) => $q->where('invoice_no', $arguments['invoice_no']))
            ->first();

        if (! $invoice) {
            return ToolResult::json([
                'status' => 'error',
                'message' => 'Invoice not found. Please provide a valid invoice_id or invoice_no.',
            ]);
        }

        return ToolResult::json([
            'status' => 'success',
            'invoice' => $invoice->append([
                'total', 'gst_amount', 'cgst', 'sgst', 'igst', 'net_total', 'is_same_state', 'type'
            ])->toArray(),
        ]);
    }
}

