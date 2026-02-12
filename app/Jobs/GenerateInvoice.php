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

            // 4. Set the price based on item type and config
            $price = 0;

            if ($itemableType === Domain::class) {
                // Get TLD extension from domain (e.g., ".com" from "example.com")
                $tldExtension = $this->extractTldExtension($item->tld);
                // Remove leading dot for config key (e.g., ".com" becomes "com")
                $tldKey = ltrim($tldExtension, '.');
                $basePrice = config("pricing.domains.{$tldKey}", 0);

                // Calculate years from invoice date to expiry date
                $years = $this->calculateYears($invoice->date, $item->expiry_date);
                $price = $basePrice * $years;
            } elseif ($itemableType === Hosting::class) {
                // Get price from hosting package, or use default
                $price = $item->package?->price ?? config('pricing.hosting.default_price', 0);
            } elseif ($itemableType === Email::class) {
                // Calculate price based on number of accounts and years
                $years = $this->calculateYears($invoice->date, $item->expiry_date);

                if ($years > 0) {
                    $pricePerAccountPerYear = config('pricing.workspace.price_per_account_per_year', 0);
                    $price = $pricePerAccountPerYear * ($item->accounts_count ?? 0) * $years;
                } else {
                    // Calculate with months calculation
                    $months = $this->calculateMonths($invoice->date, $item->expiry_date);
                    $pricePerAccountPerMonth = config('pricing.workspace.price_per_account_per_month', 0);
                    $price = $pricePerAccountPerMonth * ($item->accounts_count ?? 0) * $months;
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

    /**
     * Extract TLD extension from domain name
     * 
     * @param string $domain Full domain name (e.g., "example.com")
     * @return string TLD extension with dot (e.g., ".com")
     */
    private function extractTldExtension(string $domain): string
    {
        // Handle common multi-part TLDs first
        $multiPartTlds = ['.co.in', '.co.uk', '.com.au', '.org.uk', '.net.au', '.org.in', '.com.in', '.net.in'];

        foreach ($multiPartTlds as $tld) {
            if (str_ends_with($domain, $tld)) {
                return $tld;
            }
        }

        // Extract single-part TLD (everything after the last dot)
        $parts = explode('.', $domain);
        if (count($parts) >= 2) {
            return '.' . end($parts);
        }

        return '';
    }

    /**
     * Calculate the number of years between invoice date and expiry date
     * 
     * @param \Carbon\Carbon|string $invoiceDate
     * @param \Carbon\Carbon|string|null $expiryDate
     * @return int Number of years (minimum 1)
     */
    private function calculateYears($invoiceDate, $expiryDate): int
    {
        if (!$expiryDate) {
            return 1; // Default to 1 year if no expiry date
        }

        $invoice = \Carbon\Carbon::parse($invoiceDate);
        $expiry = \Carbon\Carbon::parse($expiryDate);

        $years = $invoice->diffInYears($expiry);

        // Return at least 1 year
        return max(1, $years);
    }

    /**
     * Calculate the number of months between invoice date and expiry date
     * 
     * @param \Carbon\Carbon|string $invoiceDate
     * @param \Carbon\Carbon|string|null $expiryDate
     * @return int Number of months (minimum 1)
     */
    private function calculateMonths($invoiceDate, $expiryDate): int
    {
        if (!$expiryDate) {
            return 12; // Default to 12 months if no expiry date
        }

        $invoice = \Carbon\Carbon::parse($invoiceDate);
        $expiry = \Carbon\Carbon::parse($expiryDate);

        $months = $invoice->diffInMonths($expiry);

        // Return at least 1 month
        return max(1, $months);
    }
}
