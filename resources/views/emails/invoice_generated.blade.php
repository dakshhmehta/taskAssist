<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Generated</title>
</head>

<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">

    <h1 style="color: #2c3e50; border-bottom: 3px solid #4CAF50; padding-bottom: 10px;">Invoice Generated</h1>

    <p>Hello {{ $invoice->client->billing_name }},</p>

    <p>A new invoice has been generated for your account.</p>

    <p>
        <strong>Invoice Number:</strong> {{ $invoice->invoice_no }}<br>
        <strong>Invoice Date:</strong> {{ $invoice->date->format('d M, Y') }}
    </p>

    <hr style="border: 0; border-top: 1px solid #ddd; margin: 20px 0;">

    <h2 style="color: #2c3e50; font-size: 18px;">Invoice Items</h2>

    <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
        <thead>
            <tr>
                <th style="border-bottom: 2px solid #ccc; text-align: left; padding: 8px; background-color: #f5f5f5;">#</th>
                <th style="border-bottom: 2px solid #ccc; text-align: left; padding: 8px; background-color: #f5f5f5;">Item</th>
                <th style="border-bottom: 2px solid #ccc; text-align: left; padding: 8px; background-color: #f5f5f5;">Type</th>
                <th style="border-bottom: 2px solid #ccc; text-align: right; padding: 8px; background-color: #f5f5f5;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->items as $i => $item)
            <tr>
                <td style="padding: 8px; border-bottom: 1px solid #eee;">{{ $i + 1 }}</td>
                <td style="padding: 8px; border-bottom: 1px solid #eee;">
                    @if($item->itemable_type === 'App\Models\Domain')
                    {{ $item->itemable->tld }}
                    @elseif($item->itemable_type === 'App\Models\Hosting')
                    {{ $item->itemable->domain }}
                    @elseif($item->itemable_type === 'App\Models\Email')
                    {{ $item->itemable->domain }} ({{ $item->itemable->accounts_count }} accounts)
                    @endif
                </td>
                <td style="padding: 8px; border-bottom: 1px solid #eee;">
                    @if($item->itemable_type === 'App\Models\Domain')
                    Domain
                    @elseif($item->itemable_type === 'App\Models\Hosting')
                    Hosting
                    @elseif($item->itemable_type === 'App\Models\Email')
                    Google Workspace
                    @endif
                </td>
                <td style="padding: 8px; border-bottom: 1px solid #eee; text-align: right;">
                    Rs. {{ number_format($item->price / 1.18, 2) }}
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <hr style="border: 0; border-top: 1px solid #ddd; margin: 20px 0;">

    <h2 style="color: #2c3e50; font-size: 18px;">Invoice Summary</h2>

    <table style="width: 100%; margin: 20px 0;">
        <tr>
            <td style="text-align: right; padding: 5px;"><strong>Subtotal:</strong></td>
            <td style="text-align: right; padding: 5px; width: 150px;">Rs. {{ number_format($invoice->total / 1.18, 2) }}</td>
        </tr>
        @if($invoice->client->account?->country == 'India')
        @if($invoice->cgst > 0)
        <tr>
            <td style="text-align: right; padding: 5px;">CGST (9%):</td>
            <td style="text-align: right; padding: 5px;">Rs. {{ number_format($invoice->cgst, 2) }}</td>
        </tr>
        @endif
        @if($invoice->sgst > 0)
        <tr>
            <td style="text-align: right; padding: 5px;">SGST (9%):</td>
            <td style="text-align: right; padding: 5px;">Rs. {{ number_format($invoice->sgst, 2) }}</td>
        </tr>
        @endif
        @if($invoice->igst > 0)
        <tr>
            <td style="text-align: right; padding: 5px;">IGST (18%):</td>
            <td style="text-align: right; padding: 5px;">Rs. {{ number_format($invoice->igst, 2) }}</td>
        </tr>
        @endif
        @endif
        <tr style="border-top: 2px solid #333;">
            <td style="text-align: right; padding: 10px 5px;"><strong>Total:</strong></td>
            <td style="text-align: right; padding: 10px 5px;"><strong>Rs. {{ number_format($invoice->total, 2) }}</strong></td>
        </tr>
    </table>

    <div style="text-align: center; margin: 30px 0;">
        <a href="{{ route('invoices.print', [$invoice->id, 'force' => 1]) }}"
            style="background-color: #4CAF50; 
                  color: white; 
                  padding: 12px 30px; 
                  text-decoration: none; 
                  border-radius: 5px; 
                  display: inline-block;
                  font-weight: bold;">
            View Invoice
        </a>
    </div>

    <hr style="border: 0; border-top: 1px solid #ddd; margin: 20px 0;">

    <p>If you have any questions about this invoice, please don't hesitate to contact us.</p>

    <p>
        Thanks,<br>
        <strong>{{ config('app.name') }}</strong>
    </p>

</body>

</html>