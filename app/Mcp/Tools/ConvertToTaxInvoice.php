<?php

namespace App\Mcp\Tools;

use App\Models\Invoice;
use Generator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\Title;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;

#[Title('Convert to Tax Invoice')]
class ConvertToTaxInvoice extends Tool
{
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
    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        return $schema->string('invoice_id', 'The ID of the invoice to convert.')
            ->string('invoice_no', 'The invoice number of the invoice to convert.');
    }

    /**
     * Execute the tool call.
     *
     * @return ToolResult|Generator
     */
    public function handle(array $arguments): ToolResult|Generator
    {
        $id = $arguments['invoice_id'] ?? null;
        $no = $arguments['invoice_no'] ?? null;

        if (!$id && !$no) {
            return ToolResult::json([
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

        if (!$invoice) {
            return ToolResult::json([
                'status' => 'error',
                'message' => 'Invoice not found.',
            ]);
        }

        try {
            $taxInvoice = $invoice->createTaxInvoice();

            return ToolResult::json([
                'status' => 'success',
                'message' => "Invoice '{$invoice->invoice_no}' converted successfully to tax invoice '{$taxInvoice->invoice_no}'.",
                'tax_invoice' => [
                    'id' => $taxInvoice->id,
                    'invoice_no' => $taxInvoice->invoice_no,
                    'date' => $taxInvoice->date->format('Y-m-d'),
                    'total' => number_format($taxInvoice->total, 2),
                    'client' => $taxInvoice->client?->name,
                ],
            ]);
        } catch (\Exception $e) {
            return ToolResult::json([
                'status' => 'error',
                'message' => 'Failed to convert invoice: ' . $e->getMessage(),
            ]);
        }
    }
}
