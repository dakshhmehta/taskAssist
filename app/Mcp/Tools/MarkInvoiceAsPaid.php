<?php

namespace App\Mcp\Tools;

use App\Models\Invoice;
use Generator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\Title;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;

#[Title('Mark Invoice As Paid')]
class MarkInvoiceAsPaid extends Tool
{
    /**
     * A description of the tool.
     */
    public function description(): string
    {
        return 'Mark an invoice as paid by invoice ID or invoice number.';
    }

    /**
     * The input schema of the tool.
     */
    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        return $schema->integer('invoice_id', 'The ID of the invoice to mark as paid.')
            ->string('invoice_no', 'The invoice number (exact match) to mark as paid, e.g. "SI-00042/2025".')
            ->string('paid_date', 'Optional. The date the payment was received (Y-m-d). Defaults to today.')
            ->string('remarks', 'Optional. Payment remarks.');
    }

    /**
     * Execute the tool call.
     *
     * @return ToolResult|Generator
     */
    public function handle(array $arguments): ToolResult|Generator
    {
        $invoiceId = $arguments['invoice_id'] ?? null;
        $invoiceNo = $arguments['invoice_no'] ?? null;
        $paidDate = $arguments['paid_date'] ?? now()->format('Y-m-d');
        $remarks = $arguments['remarks'] ?? null;

        if (! $invoiceId && ! $invoiceNo) {
            return ToolResult::json([
                'status' => 'error',
                'message' => 'Either invoice_id or invoice_no must be provided.',
            ]);
        }

        if ($invoiceId) {
            $invoice = Invoice::find($invoiceId);
        } else {
            $invoice = Invoice::where('invoice_no', $invoiceNo)->first();
        }

        if (! $invoice) {
            $identifier = $invoiceId ? "ID '{$invoiceId}'" : "number '{$invoiceNo}'";
            return ToolResult::json([
                'status' => 'error',
                'message' => "Invoice with {$identifier} not found.",
            ]);
        }

        if ($invoice->type === 'PROFORMA') {
            return ToolResult::json([
                'status' => 'error',
                'message' => "Proforma invoice '{$invoice->invoice_no}' cannot be marked as paid directly. Please convert it to a Tax Invoice first.",
            ]);
        }

        if ($invoice->paid_date !== null) {
            $message = "Invoice '{$invoice->invoice_no}' is already marked as paid on {$invoice->paid_date->format('Y-m-d')}.";
            if ($invoice->payment_remarks) {
                $message .= " Remarks: {$invoice->payment_remarks}";
            }
            return ToolResult::json([
                'status' => 'error',
                'message' => $message,
            ]);
        }

        try {
            $invoice->markAsPaid($paidDate, $remarks);

            return ToolResult::json([
                'status' => 'success',
                'message' => "Invoice '{$invoice->invoice_no}' marked as paid successfully.",
                'invoice' => [
                    'id' => $invoice->id,
                    'invoice_no' => $invoice->invoice_no,
                    'paid_date' => $invoice->paid_date->format('Y-m-d'),
                    'remarks' => $invoice->payment_remarks,
                    'total' => number_format($invoice->total, 2),
                    'client' => $invoice->client?->name,
                ],
            ]);
        } catch (\Exception $e) {
            return ToolResult::json([
                'status' => 'error',
                'message' => 'Failed to mark invoice as paid: ' . $e->getMessage(),
            ]);
        }
    }
}
