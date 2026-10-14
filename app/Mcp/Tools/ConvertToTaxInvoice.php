<?php

namespace App\Mcp\Tools;

use App\Models\Invoice;
use Generator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ConvertToTaxInvoice extends Tool
{
    protected string $title = 'Convert to Tax Invoice';

    /**
     * A description of the tool.
     */
    public function description(): string
    {
        return 'Convert a proforma invoice to a tax invoice.';
    }

    /**
     * The input schema of the tool.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'invoice_id' => $schema->string()->description('The ID of the invoice to convert.'),
            'invoice_no' => $schema->string()->description('The invoice number of the invoice to convert.'),
        ];
    }

    /**
     * Execute the tool call.
     */
    public function handle(Request $request): Response|Generator
    {
        $id = $request->get('invoice_id') ?? null;
        $no = $request->get('invoice_no') ?? null;

        if (! $id && ! $no) {
            return Response::json([
                'status' => 'error',
                'message' => 'Either invoice_id or invoice_no must be provided.',
            ]);
        }

        $query = Invoice::query();
        if ($id) {
            $query->where('id', $id);
        } else {
            $query->where('invoice_no', $no);
        }

        $invoice = $query->first();

        if (! $invoice) {
            return Response::json([
                'status' => 'error',
                'message' => 'Invoice not found.',
            ]);
        }

        try {
            $taxInvoice = $invoice->createTaxInvoice();

            return Response::json([
                'status' => 'success',
                'message' => "Invoice '{$invoice->invoice_no}' converted successfully to tax invoice '{$taxInvoice->invoice_no}'.",
                'tax_invoice' => [
                    'id' => $taxInvoice->id,
                    'invoice_no' => $taxInvoice->invoice_no,
                    'type' => $taxInvoice->type,
                    'lut_no' => $taxInvoice->lut_no,
                    'date' => $taxInvoice->date->format('Y-m-d'),
                    'total' => number_format($taxInvoice->total, 2),
                    'client' => $taxInvoice->client?->name,
                ],
            ]);
        } catch (\Exception $e) {
            return Response::json([
                'status' => 'error',
                'message' => 'Failed to convert invoice: '.$e->getMessage(),
            ]);
        }
    }
}
