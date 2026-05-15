<?php

namespace App\Mcp\Tools;

use App\Models\Invoice;
use App\Models\InvoiceExtra;
use App\Models\InvoiceItem;
use Generator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\Title;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;

#[Title('Update Invoice')]
class UpdateInvoice extends Tool
{
    public function description(): string
    {
        return 'Update invoice data including items, extras, and related itemable data (domain, hosting, etc.).';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        return $schema->integer('invoice_id', 'The ID of the invoice to update.')->required()
            ->string('date', 'Update invoice date (Y-m-d).')
            ->string('status', 'Update invoice status.')
            ->string('payment_remarks', 'Update payment remarks.')
            ->string('footnote', 'Update footnote.')
            ->array('items', 'Items to update/add. Each item can have: id, price, discount_value, line_description, expiry_date, and itemable_data (object).')
            ->array('extras', 'Extras to update/add. Each extra can have: id, line_title, line_description, line_duration, price.')
            ->array('delete_items', 'IDs of items to remove.')
            ->array('delete_extras', 'IDs of extras to remove.');
    }

    public function handle(array $arguments): ToolResult|Generator
    {
        $invoice = Invoice::with(['items.itemable', 'extras'])->find($arguments['invoice_id']);

        if (! $invoice) {
            return ToolResult::json(['status' => 'error', 'message' => 'Invoice not found.']);
        }

        // Update basic invoice fields
        $invoiceUpdateData = [];
        foreach (['date', 'status', 'payment_remarks', 'footnote'] as $field) {
            if (isset($arguments[$field])) {
                $invoiceUpdateData[$field] = $arguments[$field];
            }
        }
        if (! empty($invoiceUpdateData)) {
            $invoice->update($invoiceUpdateData);
        }

        // Handle Deletions
        if (isset($arguments['delete_items'])) {
            InvoiceItem::where('invoice_id', $invoice->id)->whereIn('id', $arguments['delete_items'])->delete();
        }
        if (isset($arguments['delete_extras'])) {
            InvoiceExtra::where('invoice_id', $invoice->id)->whereIn('id', $arguments['delete_extras'])->delete();
        }

        // Update/Add Items
        if (isset($arguments['items'])) {
            foreach ($arguments['items'] as $itemData) {
                $item = null;
                if (isset($itemData['id'])) {
                    $item = InvoiceItem::where('invoice_id', $invoice->id)->find($itemData['id']);
                }

                if ($item) {
                    $item->update(array_filter($itemData, fn($key) => in_array($key, ['price', 'discount_value', 'line_description', 'expiry_date']), ARRAY_FILTER_USE_KEY));
                } else {
                    $item = $invoice->items()->create(array_filter($itemData, fn($key) => in_array($key, ['price', 'discount_value', 'line_description', 'expiry_date', 'itemable_id', 'itemable_type']), ARRAY_FILTER_USE_KEY));
                }

                // Update Itemable data if provided
                if (isset($itemData['itemable_data']) && $item->itemable) {
                    $item->itemable->update($itemData['itemable_data']);
                }
            }
        }

        // Update/Add Extras
        if (isset($arguments['extras'])) {
            foreach ($arguments['extras'] as $extraData) {
                $extra = null;
                if (isset($extraData['id'])) {
                    $extra = InvoiceExtra::where('invoice_id', $invoice->id)->find($extraData['id']);
                }

                if ($extra) {
                    $extra->update(array_filter($extraData, fn($key) => in_array($key, ['line_title', 'line_description', 'line_duration', 'price']), ARRAY_FILTER_USE_KEY));
                } else {
                    $invoice->extras()->create(array_filter($extraData, fn($key) => in_array($key, ['line_title', 'line_description', 'line_duration', 'price']), ARRAY_FILTER_USE_KEY));
                }
            }
        }

        // Refresh and return
        $invoice->load(['items.itemable', 'extras']);
        
        return ToolResult::json([
            'status' => 'success',
            'message' => 'Invoice updated successfully.',
            'invoice' => $invoice->append(['total', 'net_total', 'type'])->toArray(),
        ]);
    }
}
