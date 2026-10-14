<?php

namespace App\Mcp\Tools;

use App\Models\Client;
use App\Models\Domain;
use App\Models\Email;
use App\Models\Hosting;
use App\Models\Invoice;
use App\Models\InvoiceExtra;
use App\Models\InvoiceItem;
use App\Services\InvoicePricingService;
use Carbon\Carbon;
use Generator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class GenerateServiceInvoice extends Tool
{
    protected string $title = 'Generate Service Invoice';

    public function description(): string
    {
        return 'Create an invoice for a client with custom items (domain, hosting, email) and optional extras. Items support auto-pricing from config when price is omitted.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'client_id' => $schema->integer()->description('The ID of the client to invoice.'),
            'items' => $schema->string()->description('Optional. JSON array of invoice items. Each item: {"itemable_type":"domain|hosting|email","itemable_id":42,"price":1500,"discount_value":0,"expiry_date":"2026-06-15","line_description":"..."} . Price auto-calculates from config if omitted.'),
            'extras' => $schema->string()->description('Optional. JSON array of extra line items. Each extra: {"line_title":"...","line_description":"...","line_duration":"...","price":500,"discount_value":0}'),
            'date' => $schema->string()->description('Optional. Invoice date (Y-m-d). Defaults to today.'),
            'invoice_prefix' => $schema->string()->description('Optional. Invoice number prefix. Auto-generates with "SR-" prefix if omitted.'),
            'footnote' => $schema->string()->description('Optional. Footnote text for the invoice.'),
        ];
    }

    public function handle(Request $request): Response|Generator
    {
        $clientId = $request->get('client_id') ?? null;
        $itemsJson = $request->get('items') ?? null;
        $extrasJson = $request->get('extras') ?? null;
        $date = $request->get('date') ?? now()->format('Y-m-d');
        $invoicePrefix = $request->get('invoice_prefix') ?? 'SR-';
        $footnote = $request->get('footnote') ?? null;

        if (! $clientId) {
            return Response::json([
                'status' => 'error',
                'message' => 'client_id is required.',
            ]);
        }

        $client = Client::find($clientId);

        if (! $client) {
            return Response::json([
                'status' => 'error',
                'message' => 'Client not found.',
            ]);
        }

        $items = [];
        if ($itemsJson) {
            $items = json_decode($itemsJson, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return Response::json([
                    'status' => 'error',
                    'message' => 'Invalid items JSON: '.json_last_error_msg(),
                ]);
            }

            if (! is_array($items)) {
                $items = [];
            }
        }

        $warnings = [];

        foreach ($items as $index => $item) {
            $type = strtolower($item['itemable_type'] ?? '');
            $itemableId = $item['itemable_id'] ?? null;

            if (! in_array($type, ['domain', 'hosting', 'email'])) {
                return Response::json([
                    'status' => 'error',
                    'message' => "Item #{$index}: Invalid itemable_type '{$type}'. Use domain, hosting, or email.",
                ]);
            }

            if (! $itemableId) {
                return Response::json([
                    'status' => 'error',
                    'message' => "Item #{$index}: itemable_id is required.",
                ]);
            }
        }

        $invoiceItems = [];

        foreach ($items as $index => $itemData) {
            $type = strtolower($itemData['itemable_type']);
            $itemableId = $itemData['itemable_id'];

            $model = match ($type) {
                'domain' => Domain::find($itemableId),
                'hosting' => Hosting::find($itemableId),
                'email' => Email::find($itemableId),
            };

            if (! $model) {
                return Response::json([
                    'status' => 'error',
                    'message' => "Item #{$index}: {$type} with ID {$itemableId} not found.",
                ]);
            }

            if ($model instanceof Hosting && ($model->is_suspended || $model->is_terminated)) {
                $state = $model->is_terminated ? 'terminated' : 'suspended';

                return Response::json([
                    'status' => 'error',
                    'message' => "Item #{$index}: hosting '{$model->domain}' is {$state} and cannot be invoiced.",
                ]);
            }

            if (! $model->client_id) {
                $model->client_id = $client->id;
                $model->save();
            } elseif ($model->client_id !== $client->id) {
                $warnings[] = "Item #{$index}: Asset already belongs to client ID {$model->client_id}, not {$client->id}. Client not reassigned.";
            }

            $price = $itemData['price'] ?? null;

            if ($price === null) {
                $invoiceDate = Carbon::parse($date);

                $price = match ($type) {
                    'domain' => InvoicePricingService::getDomainPrice(
                        $model->tld,
                        $invoiceDate,
                        $model->expiry_date
                    ),
                    'hosting' => InvoicePricingService::getHostingPrice($model),
                    'email' => InvoicePricingService::getEmailPrice(
                        $model,
                        $invoiceDate,
                        $model->expiry_date
                    ),
                };
            }

            $expiryDate = $itemData['expiry_date'] ?? ($model->expiry_date?->format('Y-m-d'));

            $invoiceItems[] = [
                'itemable_type' => get_class($model),
                'itemable_id' => $model->id,
                'price' => $price,
                'discount_value' => $itemData['discount_value'] ?? 0,
                'expiry_date' => $expiryDate,
                'line_description' => $itemData['line_description'] ?? null,
            ];
        }

        $extras = [];
        if ($extrasJson) {
            $extras = json_decode($extrasJson, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return Response::json([
                    'status' => 'error',
                    'message' => 'Invalid extras JSON: '.json_last_error_msg(),
                ]);
            }

            if (! is_array($extras)) {
                $extras = [];
            }
        }

        if (empty($invoiceItems) && empty($extras)) {
            return Response::json([
                'status' => 'error',
                'message' => 'At least one invoice item or extra is required.',
            ]);
        }

        try {
            $invoice = Invoice::create([
                'invoice_no' => Invoice::nextInvoiceNumber($invoicePrefix, $date),
                'date' => $date,
                'client_id' => $client->id,
            ]);

            foreach ($invoiceItems as $itemData) {
                InvoiceItem::create(array_merge(['invoice_id' => $invoice->id], $itemData));
            }

            foreach ($extras as $extraData) {
                InvoiceExtra::create([
                    'invoice_id' => $invoice->id,
                    'line_title' => $extraData['line_title'] ?? '',
                    'line_description' => $extraData['line_description'] ?? '',
                    'line_duration' => $extraData['line_duration'] ?? null,
                    'price' => $extraData['price'] ?? 0,
                    'discount_value' => $extraData['discount_value'] ?? 0,
                ]);
            }

            if ($footnote) {
                $invoice->footnote = $footnote;
                $invoice->save();
            }

            $invoice->load(['client.account', 'items.itemable', 'extras', 'taxInvoice', 'proformaInvoice']);

            $response = [
                'status' => 'success',
                'message' => "Invoice '{$invoice->invoice_no}' created successfully for {$client->display_name}.",
                'invoice' => $invoice->append([
                    'total', 'gst_amount', 'cgst', 'sgst', 'igst', 'net_total', 'is_same_state', 'type', 'lut_no',
                ])->toArray(),
            ];

            if (! empty($warnings)) {
                $response['warnings'] = $warnings;
            }

            return Response::json($response);
        } catch (\Exception $e) {
            return Response::json([
                'status' => 'error',
                'message' => 'Failed to create invoice: '.$e->getMessage(),
            ]);
        }
    }
}
