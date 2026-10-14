<?php

namespace App\Mcp\Tools;

use App\Models\Invoice;
use Generator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class MarkInvoiceAsPaid extends Tool
{
    protected string $title = 'Mark Invoice As Paid';

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
    public function schema(JsonSchema $schema): array
    {
        return [
            'invoice_id' => $schema->integer()->description('The ID of the invoice to mark as paid.'),
            'invoice_no' => $schema->string()->description('The invoice number (exact match) to mark as paid, e.g. "SI-00042/2025".'),
            'paid_date' => $schema->string()->description('Optional. The date the payment was received (Y-m-d). Defaults to today.'),
            'remarks' => $schema->string()->description('Optional. Payment remarks.'),
        ];
    }

    /**
     * Execute the tool call.
     */
    public function handle(Request $request): Response|Generator
    {
        $invoiceId = $request->get('invoice_id') ?? null;
        $invoiceNo = $request->get('invoice_no') ?? null;
        $paidDate = $request->get('paid_date') ?? now()->format('Y-m-d');
        $remarks = $request->get('remarks') ?? null;

        if (! $invoiceId && ! $invoiceNo) {
            return Response::json([
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

            return Response::json([
                'status' => 'error',
                'message' => "Invoice with {$identifier} not found.",
            ]);
        }

        if ($invoice->type === 'PROFORMA') {
            return Response::json([
                'status' => 'error',
                'message' => "Proforma invoice '{$invoice->invoice_no}' cannot be marked as paid directly. Please convert it to a Tax Invoice first.",
            ]);
        }

        if ($invoice->paid_date !== null) {
            $message = "Invoice '{$invoice->invoice_no}' is already marked as paid on {$invoice->paid_date->format('Y-m-d')}.";
            if ($invoice->payment_remarks) {
                $message .= " Remarks: {$invoice->payment_remarks}";
            }

            return Response::json([
                'status' => 'error',
                'message' => $message,
            ]);
        }

        try {
            $invoice->markAsPaid($paidDate, $remarks);

            return Response::json([
                'status' => 'success',
                'message' => "Invoice '{$invoice->invoice_no}' marked as paid successfully.",
                'invoice' => [
                    'id' => $invoice->id,
                    'invoice_no' => $invoice->invoice_no,
                    'type' => $invoice->type,
                    'lut_no' => $invoice->lut_no,
                    'paid_date' => $invoice->paid_date->format('Y-m-d'),
                    'remarks' => $invoice->payment_remarks,
                    'total' => number_format($invoice->total, 2),
                    'client' => $invoice->client?->name,
                ],
            ]);
        } catch (\Exception $e) {
            return Response::json([
                'status' => 'error',
                'message' => 'Failed to mark invoice as paid: '.$e->getMessage(),
            ]);
        }
    }
}
