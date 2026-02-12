# GenerateInvoice Job

## Overview
This job automatically generates invoices for Domain, Hosting, or Email services and sends an email notification to the client.

## Features
1. **Auto-generates invoice number** using `Invoice::nextInvoiceNumber('DH-')` helper
2. **Sets invoice date** to current date (or custom date if provided)
3. **Auto-detects client** from the first item in the array
4. **Sets prices from config with dynamic period calculation**:
   - **Domains**: Uses TLD-based pricing × number of years (calculated from invoice date to expiry date)
   - **Hosting**: Uses package price, or default if no package
   - **Email/Workspace**: Calculates price based on accounts count × years × yearly rate
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

## Pricing Configuration

Prices are configured in `config/pricing.php`:

### Domain Pricing

```php
'domains' => [
    'com' => 1460,
    'in' => 960,
    'org' => 1599,
    'co.in' => 9000,
    'org.in' => 885,
    // Add more TLDs as needed
],
```

- Prices should include GST (18%)
- Price is **per year**
- Keys are TLD extensions **without the leading dot** (e.g., "com" not ".com")
- The job extracts the TLD from the domain name and strips the dot for lookup
- Supports multi-part TLDs like "co.in", "org.in", "com.in"
- **Important**: Multi-part TLDs use array access (`$config['co.in']`) instead of dot notation to avoid Laravel interpreting dots as nested keys
- **Automatically multiplies by number of years**: Calculates years from invoice date to expiry date
  - Example: 2-year domain registration = `1460 × 2 = 2920`

### Workspace (Email) Pricing

```php
'workspace' => [
    'price_per_account_per_year' => 2832.00, // 236 × 12
],
```

- Price is **per account per year**
- Price should include GST (18%)
- **Automatically multiplies by number of years**: Calculates years from invoice date to expiry date
  - 1-year subscription: `2832 × accounts × 1`
  - 2-year subscription: `2832 × accounts × 2`
- Example: 5 accounts for 2 years = `2832 × 5 × 2 = 28,320`

### Hosting Pricing

```php
'hosting' => [
    'default_price' => 0,
],
```

- Hosting uses the price from the `HostingPackage` relationship
- If no package is assigned, uses the default price from config
- Package prices should include GST (18%)
- Hosting prices are **not** multiplied by period (annual price expected)

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
- Pricing must be configured in `config/pricing.php`
- For hosting items, package relationship is optional (uses default if not set)
- For email items, `accounts_count` field is required for price calculation

## Error Handling

The job will throw exceptions if:
- No items are provided
- The first item has no associated client
- Any required relationships are missing
