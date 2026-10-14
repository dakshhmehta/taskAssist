<?php

namespace App\Mcp\Tools;

use App\Models\Invoice;
use Generator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class GetInvoice extends Tool
{
    protected string $title = 'Get Invoice';

    public function description(): string
    {
        return 'Get full invoice details by invoice ID or invoice number. Returns client, account, items, and financial totals.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'invoice_id' => $schema->integer()->description('The ID of the invoice to retrieve.'),
            'invoice_no' => $schema->string()->description('The invoice number to look up (e.g., "DH-00123/2025").'),
        ];
    }

    public function handle(Request $request): Response|Generator
    {
        $invoice = Invoice::query()
            ->with(['client.account', 'items.itemable', 'extras', 'taxInvoice', 'proformaInvoice'])
            ->when($request->get('invoice_id') !== null, fn ($q) => $q->where('id', $request->get('invoice_id')))
            ->when(! $request->get('invoice_id') !== null && $request->get('invoice_no') !== null, fn ($q) => $q->where('invoice_no', $request->get('invoice_no')))
            ->first();

        if (! $invoice) {
            return Response::json([
                'status' => 'error',
                'message' => 'Invoice not found. Please provide a valid invoice_id or invoice_no.',
            ]);
        }

        return Response::json([
            'status' => 'success',
            'invoice' => $invoice->append([
                'total', 'gst_amount', 'cgst', 'sgst', 'igst', 'net_total', 'is_same_state', 'type', 'lut_no',
            ])->toArray(),
        ]);
    }
}
