<?php

namespace App\Jobs;

use App\Models\Domain;
use App\Models\Email;
use App\Models\Hosting;
use App\Models\Invoice;
use App\Models\InvoiceExtra;
use App\Models\InvoiceItem;
use App\Services\InvoicePricingService;
use App\Support\InvoiceTerm;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateInvoice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The items to generate invoice for (array of Domain, Hosting, or Email models)
     */
    public $items;

    public $invoiceDate;

    /**
     * Extra ad-hoc line items, each: ['line_title' => ..., 'price' => ..., 'discount_value' => ..., ...]
     */
    public $extras;

    public $footnote;

    /**
     * Per-item discount values keyed by the item index in $items.
     */
    public $itemDiscounts;

    /**
     * Number of years to advance the domain/hosting line expiry by.
     */
    public $term = 0;

    /**
     * Create a new job instance.
     *
     * @param array $items Array of Domain, Hosting, or Email objects
     */
    public function __construct(array $items, $invoiceDate = null, array $extras = [], ?string $footnote = null, array $itemDiscounts = [], int $term = 0)
    {
        $this->items = $items;
        $this->invoiceDate = $invoiceDate;
        $this->extras = $extras;
        $this->footnote = $footnote;
        $this->itemDiscounts = $itemDiscounts;
        $this->term = $term;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (empty($this->items)) {
            throw new \Exception('No items provided for invoice generation.');
        }

        // Suspended/terminated hostings must never appear on an invoice.
        $this->items = array_values(array_filter(
            $this->items,
            fn ($item) => ! ($item instanceof Hosting && ($item->is_suspended || $item->is_terminated))
        ));

        if (empty($this->items)) {
            throw new \Exception('No billable items provided for invoice generation.');
        }

        // 1. Get the first item to determine client
        $firstItem = $this->items[0];

        // 2. Set the client by taking the client id of the first itemable object's client relation
        $client = $firstItem->client;

        if (!$client) {
            throw new \Exception('No client found for the first item.');
        }

        // 1. Set the Invoice Date to Today and Serial number with nextInvoiceNumber helper method
        $invoice = Invoice::create([
            'invoice_no' => Invoice::nextInvoiceNumber('DH-', $this->invoiceDate),
            'date' => $this->invoiceDate ?? now(),
            'client_id' => $client->id,
        ]);


        // 3. Add all items array to the invoice items
        foreach ($this->items as $index => $item) {
            if ($item == null) continue;

            // Determine the itemable type
            $itemableType = get_class($item);

            // Domain and hosting lines can be advanced by the requested term.
            $shiftExpiry = in_array($itemableType, [Domain::class, Hosting::class], true);
            $expiry = $shiftExpiry
                ? InvoiceTerm::shift($item->expiry_date, $this->term)
                : $item->expiry_date;

            // 4. Set the price based on item type and config
            $price = 0;

            if ($itemableType === Domain::class) {
                $price = InvoicePricingService::getDomainPrice($item->tld, $invoice->date, $expiry);
            } elseif ($itemableType === Hosting::class) {
                $price = InvoicePricingService::getHostingPrice($item, max(1, $this->term));
            } elseif ($itemableType === Email::class) {
                $price = InvoicePricingService::getEmailPrice($item, $invoice->date, $item->expiry_date);
            }

            // 5. Copy the expiry date as we did in InvoiceResource
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'itemable_type' => $itemableType,
                'itemable_id' => $item->id,
                'price' => $price,
                'discount_value' => $this->itemDiscounts[$index] ?? 0,
                'expiry_date' => $expiry,
            ]);
        }

        foreach ($this->extras as $extra) {
            InvoiceExtra::create([
                'invoice_id' => $invoice->id,
                'line_title' => $extra['line_title'] ?? '',
                'line_description' => $extra['line_description'] ?? '',
                'line_duration' => $extra['line_duration'] ?? '',
                'price' => $extra['price'] ?? 0,
                'discount_value' => $extra['discount_value'] ?? 0,
            ]);
        }

        if ($this->footnote) {
            $invoice->footnote = $this->footnote;
            $invoice->save();
        }

        // 6. Send a copy of invoice along with View Invoice button and invoice items, sub total, gst and grand total details as body
        EmailInvoice::dispatch($invoice, $firstItem, $this->extras[0]['line_title'] ?? null);
    }
}
