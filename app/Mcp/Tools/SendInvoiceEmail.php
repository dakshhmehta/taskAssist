<?php

namespace App\Mcp\Tools;

use App\Jobs\EmailInvoice as EmailInvoiceJob;
use App\Models\Invoice;
use Generator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class SendInvoiceEmail extends Tool
{
    protected string $title = 'Send Invoice Email';

    public function description(): string
    {
        return 'Send an invoice email to the client by invoice ID or invoice number.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'invoice_id' => $schema->integer()->description('The ID of the invoice to email.'),
            'invoice_no' => $schema->string()->description('The invoice number to look up.'),
        ];
    }

    public function handle(Request $request): Response|Generator
    {
        $invoiceId = $request->get('invoice_id') ?? null;
        $invoiceNo = $request->get('invoice_no') ?? null;

        if (! $invoiceId && ! $invoiceNo) {
            return Response::json(['status' => 'error', 'message' => 'Either invoice_id or invoice_no must be provided.']);
        }

        $invoice = Invoice::query()
            ->when($invoiceId, fn ($q) => $q->where('id', $invoiceId))
            ->when(! $invoiceId && $invoiceNo, fn ($q) => $q->where('invoice_no', $invoiceNo))
            ->with(['items.itemable', 'extras', 'client'])
            ->first();

        if (! $invoice) {
            return Response::json(['status' => 'error', 'message' => 'Invoice not found.']);
        }

        $firstItem = $invoice->items->first()?->itemable;
        $firstExtraTitle = $invoice->extras->first()?->line_title;

        if (! $firstItem && ! $firstExtraTitle) {
            return Response::json(['status' => 'error', 'message' => 'Invoice has no items (domain/hosting/email) or extras. Cannot determine email subject.']);
        }

        EmailInvoiceJob::dispatch($invoice, $firstItem, $firstExtraTitle);

        return Response::json([
            'status' => 'success',
            'message' => "Invoice email for '{$invoice->invoice_no}' has been queued for sending to ".($invoice->client->email ?? 'default recipient').'.',
        ]);
    }
}
