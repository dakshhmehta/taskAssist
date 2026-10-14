<?php

namespace App\Mcp\Tools;

use App\Models\Invoice;
use App\Models\InvoiceExtra;
use App\Models\InvoiceItem;
use Generator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class UpdateInvoice extends Tool
{
    protected string $title = 'Update Invoice';

    public function description(): string
    {
        return 'Update invoice data including items, extras, and related itemable data (domain, hosting, etc.).';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'invoice_id' => $schema->integer()->description('The ID of the invoice to update.')->required(),
            'date' => $schema->string()->description('Update invoice date (Y-m-d).'),
            'status' => $schema->string()->description('Update invoice status.'),
            'payment_remarks' => $schema->string()->description('Update payment remarks.'),
            'footnote' => $schema->string()->description('Update footnote.'),
            'items' => $schema->array()->description('Items to update/add. Each item can have: id, price, discount_value, line_description, expiry_date, and itemable_data (object).'),
            'extras' => $schema->array()->description('Extras to update/add. Each extra can have: id, line_title, line_description, line_duration, price.'),
            'delete_items' => $schema->array()->description('IDs of items to remove.'),
            'delete_extras' => $schema->array()->description('IDs of extras to remove.'),
        ];
    }

    public function handle(Request $request): Response|Generator
    {
        $invoice = Invoice::with(['items.itemable', 'extras'])->find($request->get('invoice_id'));

        if (! $invoice) {
            return Response::json(['status' => 'error', 'message' => 'Invoice not found.']);
        }

        // Update basic invoice fields
        $invoiceUpdateData = [];
        foreach (['date', 'status', 'payment_remarks', 'footnote'] as $field) {
            if ($request->get($field) !== null) {
                $invoiceUpdateData[$field] = $request->get($field);
            }
        }
        if (! empty($invoiceUpdateData)) {
            $invoice->update($invoiceUpdateData);
        }

        // Handle Deletions
        if ($request->get('delete_items') !== null) {
            InvoiceItem::where('invoice_id', $invoice->id)->whereIn('id', $request->get('delete_items'))->delete();
        }
        if ($request->get('delete_extras') !== null) {
            InvoiceExtra::where('invoice_id', $invoice->id)->whereIn('id', $request->get('delete_extras'))->delete();
        }

        // Update/Add Items
        if ($request->get('items') !== null) {
            foreach ($request->get('items') as $itemData) {
                $item = null;
                if (isset($itemData['id'])) {
                    $item = InvoiceItem::where('invoice_id', $invoice->id)->find($itemData['id']);
                }

                if ($item) {
                    $item->update(array_filter($itemData, fn ($key) => in_array($key, ['price', 'discount_value', 'line_description', 'expiry_date']), ARRAY_FILTER_USE_KEY));
                } else {
                    $item = $invoice->items()->create(array_filter($itemData, fn ($key) => in_array($key, ['price', 'discount_value', 'line_description', 'expiry_date', 'itemable_id', 'itemable_type']), ARRAY_FILTER_USE_KEY));
                }

                // Update Itemable data if provided
                if (isset($itemData['itemable_data']) && $item->itemable) {
                    $item->itemable->update($itemData['itemable_data']);
                }
            }
        }

        // Update/Add Extras
        if ($request->get('extras') !== null) {
            foreach ($request->get('extras') as $extraData) {
                $extra = null;
                if (isset($extraData['id'])) {
                    $extra = InvoiceExtra::where('invoice_id', $invoice->id)->find($extraData['id']);
                }

                if ($extra) {
                    $extra->update(array_filter($extraData, fn ($key) => in_array($key, ['line_title', 'line_description', 'line_duration', 'price']), ARRAY_FILTER_USE_KEY));
                } else {
                    $invoice->extras()->create(array_filter($extraData, fn ($key) => in_array($key, ['line_title', 'line_description', 'line_duration', 'price']), ARRAY_FILTER_USE_KEY));
                }
            }
        }

        // Refresh and return
        $invoice->load(['items.itemable', 'extras']);

        return Response::json([
            'status' => 'success',
            'message' => 'Invoice updated successfully.',
            'invoice' => $invoice->append(['total', 'net_total', 'type', 'lut_no'])->toArray(),
        ]);
    }
}
