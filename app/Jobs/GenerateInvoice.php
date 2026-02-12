<?php

namespace App\Jobs;

use App\Mail\InvoiceGenerated;
use App\Models\Domain;
use App\Models\Email;
use App\Models\Hosting;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class GenerateInvoice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The items to generate invoice for (array of Domain, Hosting, or Email models)
     */
    public $items;

    public $invoiceDate;

    /**
     * Create a new job instance.
     *
     * @param array $items Array of Domain, Hosting, or Email objects
     */
    public function __construct(array $items, $invoiceDate = null)
    {
        $this->items = $items;
        $this->invoiceDate = $invoiceDate;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (empty($this->items)) {
            throw new \Exception('No items provided for invoice generation.');
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
            'invoice_no' => Invoice::nextInvoiceNumber('DH-'),
            'date' => $this->invoiceDate ?? now(),
            'client_id' => $client->id,
        ]);


        // 3. Add all items array to the invoice items
        foreach ($this->items as $item) {
            if ($item == null) continue;

            // Determine the itemable type
            $itemableType = get_class($item);

            // 4. Set the price from the previous invoice of each individual item, if no previous invoice is found, set to 0
            $lastInvoice = $item->getLastInvoice();
            $price = 0;

            if ($lastInvoice) {
                // Get the last invoice item for this specific itemable
                $lastInvoiceItem = $lastInvoice->items()
                    ->where('itemable_type', $itemableType)
                    ->where('itemable_id', $item->id)
                    ->first();

                if ($lastInvoiceItem) {
                    $price = $lastInvoiceItem->price;
                }
            }

            // 5. Copy the expiry date as we did in InvoiceResource
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'itemable_type' => $itemableType,
                'itemable_id' => $item->id,
                'price' => $price,
                'expiry_date' => $item->expiry_date ?? null,
            ]);
        }

        // 6. Send a copy of invoice along with View Invoice button and invoice items, sub total, gst and grand total details as body
        // Subject line as first line item's domain and type [Domain/Hosting/Workspace]
        $recipientEmail = $client->email ?? 'rominjoshi@yahoo.com';

        if ($recipientEmail) {
            Mail::to($recipientEmail)->send(new InvoiceGenerated($invoice, $firstItem));
        }
    }
}
