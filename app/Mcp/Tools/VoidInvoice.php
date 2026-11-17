<?php

namespace App\Mcp\Tools;

use App\Models\Invoice;
use Generator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class VoidInvoice extends Tool
{
    protected string $title = 'Void Invoice';

    /**
     * A description of the tool.
     */
    public function description(): string
    {
        return 'Void (cancel) an invoice by invoice ID or invoice number. A reason is mandatory. Proforma invoices can be voided until they are converted; tax invoices can only be voided on the day they were issued. Voided invoices are excluded from receivables and cannot be paid, converted, edited or emailed.';
    }

    /**
     * The input schema of the tool.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'invoice_id' => $schema->integer()->description('The ID of the invoice to void.'),
            'invoice_no' => $schema->string()->description('The invoice number (exact match) to void, e.g. "SR-00005/2026".'),
            'reason' => $schema->string()->description('Why the invoice is being cancelled. Required and stored on the invoice.')->required(),
        ];
    }

    /**
     * Execute the tool call.
     */
    public function handle(Request $request): Response|Generator
    {
        $invoiceId = $request->get('invoice_id') ?? null;
        $invoiceNo = $request->get('invoice_no') ?? null;
        $reason = trim((string) $request->get('reason'));

        if (! $invoiceId && ! $invoiceNo) {
            return Response::json([
                'status' => 'error',
                'message' => 'Either invoice_id or invoice_no must be provided.',
            ]);
        }

        if ($reason === '') {
            return Response::json([
                'status' => 'error',
                'message' => 'A reason is required to void an invoice.',
            ]);
        }

        $invoice = $invoiceId
            ? Invoice::find($invoiceId)
            : Invoice::where('invoice_no', $invoiceNo)->first();

        if (! $invoice) {
            $identifier = $invoiceId ? "ID '{$invoiceId}'" : "number '{$invoiceNo}'";

            return Response::json([
                'status' => 'error',
                'message' => "Invoice with {$identifier} not found.",
            ]);
        }

        if ($invoice->isVoid()) {
            $message = "Invoice '{$invoice->invoice_no}' is already void";
            if ($invoice->void_reason) {
                $message .= ". Reason: {$invoice->void_reason}";
            }

            return Response::json([
                'status' => 'error',
                'message' => $message.'.',
            ]);
        }

        if ($invoice->paid_date !== null) {
            return Response::json([
                'status' => 'error',
                'message' => "Invoice '{$invoice->invoice_no}' was paid on {$invoice->paid_date->format('Y-m-d')} and cannot be voided. Issue a credit note instead.",
            ]);
        }

        if ($invoice->type === 'TAX' && ! $invoice->date->isToday()) {
            return Response::json([
                'status' => 'error',
                'message' => "Tax invoice '{$invoice->invoice_no}' can only be voided on the day it was created (invoice date {$invoice->date->format('Y-m-d')}).",
            ]);
        }

        if ($invoice->hasTaxInvoice()) {
            return Response::json([
                'status' => 'error',
                'message' => "Invoice '{$invoice->invoice_no}' has already been converted to a tax invoice. Void the tax invoice instead.",
            ]);
        }

        try {
            $invoice->void($reason);

            return Response::json([
                'status' => 'success',
                'message' => "Invoice '{$invoice->invoice_no}' voided successfully.",
                'invoice' => [
                    'id' => $invoice->id,
                    'invoice_no' => $invoice->invoice_no,
                    'type' => $invoice->type,
                    'lut_no' => $invoice->lut_no,
                    'status' => $invoice->status,
                    'void_reason' => $invoice->void_reason,
                    'voided_at' => $invoice->voided_at?->format('Y-m-d H:i:s'),
                    'total' => number_format($invoice->total, 2),
                    'client' => $invoice->client?->name,
                ],
            ]);
        } catch (\Exception $e) {
            return Response::json([
                'status' => 'error',
                'message' => 'Failed to void invoice: '.$e->getMessage(),
            ]);
        }
    }
}
