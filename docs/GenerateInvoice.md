# GenerateInvoice Job

## Overview
This job automatically generates invoices for Domain, Hosting, or Email services and sends an email notification to the client.

## Features
1. **Auto-generates invoice number** using `Invoice::nextInvoiceNumber('DH-')` helper
2. **Sets invoice date** to current date
3. **Auto-detects client** from the first item in the array
4. **Copies prices** from previous invoices (or sets to 0 if no previous invoice exists)
5. **Copies expiry dates** from the itemable objects
6. **Sends email notification** with invoice details and a "View Invoice" button

## Usage

### Basic Example

```php
use App\Jobs\GenerateInvoice;
use App\Models\Domain;
use App\Models\Hosting;
use App\Models\Email;

// Example 1: Generate invoice for domains
$domains = Domain::whereIn('id', [1, 2, 3])->get()->all();
GenerateInvoice::dispatch($domains);

// Example 2: Generate invoice for hostings
$hostings = Hosting::whereIn('id', [4, 5])->get()->all();
GenerateInvoice::dispatch($hostings);

// Example 3: Generate invoice for emails
$emails = Email::whereIn('id', [6])->get()->all();
GenerateInvoice::dispatch($emails);

// Example 4: Mixed items (all must belong to same client)
$items = [
    Domain::find(1),
    Hosting::find(2),
    Email::find(3),
];
GenerateInvoice::dispatch($items);
```

### Queue the Job

```php
// Dispatch to queue (recommended for production)
GenerateInvoice::dispatch($items);

// Dispatch immediately (synchronous)
GenerateInvoice::dispatchSync($items);

// Dispatch with delay
GenerateInvoice::dispatch($items)->delay(now()->addMinutes(5));
```

### From Artisan Command

```php
use App\Jobs\GenerateInvoice;
use App\Models\Domain;

class GenerateRenewalInvoices extends Command
{
    public function handle()
    {
        // Get domains expiring soon
        $domains = Domain::where('expiry_date', '<=', now()->addDays(30))
            ->where('is_invoiced', false)
            ->get()
            ->groupBy('client_id');

        foreach ($domains as $clientId => $clientDomains) {
            GenerateInvoice::dispatch($clientDomains->all());
        }
    }
}
```

## Email Details

The job sends an email with:
- **Subject**: `{domain} - {type} Invoice` (e.g., "example.com - Domain Invoice")
- **Content**: 
  - Invoice number and date
  - Itemized list of services
  - Subtotal, GST breakdown, and grand total
  - "View Invoice" button linking to the invoice

## Requirements

- All items in the array must belong to the same client
- Items must have a `client` relationship defined
- Items must have an `expiry_date` field (nullable)
- Items must have a `getLastInvoice()` method

## Error Handling

The job will throw exceptions if:
- No items are provided
- The first item has no associated client
- Any required relationships are missing
