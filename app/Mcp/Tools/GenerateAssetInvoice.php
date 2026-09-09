<?php

namespace App\Mcp\Tools;

use App\Jobs\GenerateInvoice as GenerateInvoiceJob;
use App\Models\Client;
use App\Models\Domain;
use App\Models\Email;
use App\Models\Hosting;
use Generator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\Title;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;

#[Title('Generate Invoice')]
class GenerateAssetInvoice extends Tool
{
    /**
     * A description of the tool.
     */
    public function description(): string
    {
        return 'Generate a DH invoice for a specific domain, hosting, or email asset — or for a cart of multiple assets and extra line items billed together.';
    }

    /**
     * The input schema of the tool.
     */
    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        return $schema->string('domain', 'The domain name to generate the invoice for. Required unless cart is provided.')
            ->string('type', 'The type of asset (domain, hosting, email). Defaults to domain if not specified.')
            ->string('cart', 'Optional. JSON array invoiced together. Asset entry: {"type":"domain|hosting|email","domain":"example.com","discount_value":0}. Extra entry: {"type":"extra","line_title":"...","price":500,"discount_value":0,"line_description":"...","line_duration":"..."}.')
            ->string('footnote', 'Optional. Custom footnote text stored on the invoice.')
            ->string('invoice_date', 'Optional. The date for the invoice (Y-m-d). Defaults to today.')
            ->integer('client_id', 'Optional. The ID of the client to assign to the asset if not already assigned.');
    }

    /**
     * Execute the tool call.
     */
    public function handle(array $arguments): ToolResult|Generator
    {
        $cartJson = $arguments['cart'] ?? null;
        $footnote = $arguments['footnote'] ?? null;
        $invoiceDate = $arguments['invoice_date'] ?? null;
        $clientIdInput = $arguments['client_id'] ?? null;

        if ($cartJson) {
            return $this->handleCart($cartJson, $footnote, $invoiceDate, $clientIdInput);
        }

        $domainName = $arguments['domain'] ?? null;

        if (! $domainName) {
            return ToolResult::json([
                'status' => 'error',
                'message' => 'Either domain or cart must be provided.',
            ]);
        }

        $type = strtolower($arguments['type'] ?? 'domain');

        if (! in_array($type, ['domain', 'hosting', 'email'], true)) {
            return ToolResult::json([
                'status' => 'error',
                'message' => "Invalid type '{$type}'. Supported types are: domain, hosting, email.",
            ]);
        }

        $model = $this->resolveAsset($type, $domainName);

        if (! $model) {
            return ToolResult::json([
                'status' => 'error',
                'message' => "Asset of type '{$type}' with domain '{$domainName}' not found.",
            ]);
        }

        $warning = $this->assignClient($model, $domainName, $clientIdInput);

        if (is_string($warning) && str_starts_with($warning, 'error:')) {
            return ToolResult::json([
                'status' => 'error',
                'message' => substr($warning, 6),
            ]);
        }

        if (! $model->client_id) {
            return ToolResult::json([
                'status' => 'error',
                'message' => "Asset '{$domainName}' ({$type}) does not have a client assigned. Cannot generate invoice.",
            ]);
        }

        try {
            // Start with the resolved asset; domain type additionally bundles related assets.
            $items = [$model];

            // If we adding domain, and has hosting, include hosting as well
            if ($type === 'domain') {
                $hosting = Hosting::where('domain', $domainName)->first();
                if ($hosting) {
                    $items[] = $hosting;
                }

                // Check for email as well
                $email = Email::where('domain', $domainName)->first();
                if ($email) {
                    $items[] = $email;
                }
            }

            // Dispatch the job synchronously to get immediate results
            GenerateInvoiceJob::dispatchSync($items, $invoiceDate, [], $footnote);

            // Fetch the newly created invoice for this asset
            $lastInvoice = $model->getLastInvoice();

            $response = [
                'status' => 'success',
                'message' => "Invoice generated successfully for '{$domainName}' ({$type}).".($warning ? " Warning: {$warning}" : ''),
                'invoice' => $lastInvoice ? [
                    'id' => $lastInvoice->id,
                    'invoice_no' => $lastInvoice->invoice_no,
                    'type' => $lastInvoice->type,
                    'lut_no' => $lastInvoice->lut_no,
                    'date' => $lastInvoice->date->format('Y-m-d'),
                    'client' => $lastInvoice->client?->name,
                    'total' => $lastInvoice->total,
                ] : null,
            ];

            if ($warning) {
                $response['warning'] = $warning;
            }

            return ToolResult::json($response);
        } catch (\Exception $e) {
            return ToolResult::json([
                'status' => 'error',
                'message' => 'Failed to generate invoice: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Handle a multi-entry cart billed together on one DH invoice.
     */
    protected function handleCart(string $cartJson, ?string $footnote, ?string $invoiceDate, mixed $clientIdInput): ToolResult|Generator
    {
        $entries = json_decode($cartJson, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return ToolResult::json([
                'status' => 'error',
                'message' => 'Invalid cart JSON: '.json_last_error_msg(),
            ]);
        }

        if (! is_array($entries) || empty($entries)) {
            return ToolResult::json([
                'status' => 'error',
                'message' => 'Cart must be a non-empty JSON array.',
            ]);
        }

        $items = [];
        $itemDiscounts = [];
        $extras = [];
        $clientId = null;
        $labels = [];

        foreach ($entries as $index => $entry) {
            if (! is_array($entry)) {
                return ToolResult::json([
                    'status' => 'error',
                    'message' => "Cart entry #{$index} must be an object.",
                ]);
            }

            $type = strtolower($entry['type'] ?? '');

            if ($type === 'extra') {
                if (empty($entry['line_title'])) {
                    return ToolResult::json([
                        'status' => 'error',
                        'message' => "Cart entry #{$index}: line_title is required for extra entries.",
                    ]);
                }

                $extras[] = [
                    'line_title' => $entry['line_title'],
                    'line_description' => $entry['line_description'] ?? '',
                    'line_duration' => $entry['line_duration'] ?? '',
                    'price' => $entry['price'] ?? 0,
                    'discount_value' => $entry['discount_value'] ?? 0,
                ];
                $labels[] = $entry['line_title'];

                continue;
            }

            if (! in_array($type, ['domain', 'hosting', 'email'], true)) {
                return ToolResult::json([
                    'status' => 'error',
                    'message' => "Cart entry #{$index}: Invalid type '{$type}'. Use domain, hosting, email, or extra.",
                ]);
            }

            $entryDomain = $entry['domain'] ?? null;

            if (! $entryDomain) {
                return ToolResult::json([
                    'status' => 'error',
                    'message' => "Cart entry #{$index}: domain is required for asset entries.",
                ]);
            }

            $model = $this->resolveAsset($type, $entryDomain);

            if (! $model) {
                return ToolResult::json([
                    'status' => 'error',
                    'message' => "Cart entry #{$index}: Asset of type '{$type}' with domain '{$entryDomain}' not found.",
                ]);
            }

            $clientError = $this->assignClient($model, $entryDomain, $clientIdInput);

            if (is_string($clientError) && str_starts_with($clientError, 'error:')) {
                return ToolResult::json([
                    'status' => 'error',
                    'message' => "Cart entry #{$index}: ".substr($clientError, 6),
                ]);
            }

            if (! $model->client_id) {
                return ToolResult::json([
                    'status' => 'error',
                    'message' => "Cart entry #{$index}: Asset '{$entryDomain}' ({$type}) does not have a client assigned. Provide client_id.",
                ]);
            }

            if ($clientId === null) {
                $clientId = $model->client_id;
            } elseif ($model->client_id !== $clientId) {
                return ToolResult::json([
                    'status' => 'error',
                    'message' => "Cart entry #{$index}: Asset '{$entryDomain}' belongs to client ID {$model->client_id}, but the cart is for client ID {$clientId}. All cart assets must belong to the same client.",
                ]);
            }

            $itemDiscounts[count($items)] = $entry['discount_value'] ?? 0;
            $items[] = $model;
            $labels[] = "{$entryDomain} ({$type})";
        }

        if (empty($items) && empty($extras)) {
            return ToolResult::json([
                'status' => 'error',
                'message' => 'Cart must contain at least one asset or extra entry.',
            ]);
        }

        if (empty($items) && $clientIdInput === null) {
            return ToolResult::json([
                'status' => 'error',
                'message' => 'A cart with only extras requires client_id.',
            ]);
        }

        try {
            if (! empty($items)) {
                GenerateInvoiceJob::dispatchSync($items, $invoiceDate, $extras, $footnote, $itemDiscounts);
                $lastInvoice = $items[0]->getLastInvoice();
            } else {
                $client = Client::find($clientIdInput);

                if (! $client) {
                    return ToolResult::json([
                        'status' => 'error',
                        'message' => "Client with ID {$clientIdInput} does not exist.",
                    ]);
                }

                $lastInvoice = \App\Models\Invoice::create([
                    'invoice_no' => \App\Models\Invoice::nextInvoiceNumber('DH-'),
                    'date' => $invoiceDate ?? now(),
                    'client_id' => $client->id,
                ]);

                foreach ($extras as $extra) {
                    \App\Models\InvoiceExtra::create(array_merge(['invoice_id' => $lastInvoice->id], $extra));
                }

                if ($footnote) {
                    $lastInvoice->footnote = $footnote;
                    $lastInvoice->save();
                }
            }

            return ToolResult::json([
                'status' => 'success',
                'message' => 'Cart invoice generated successfully for: '.implode(', ', $labels).'.',
                'invoice' => $lastInvoice ? [
                    'id' => $lastInvoice->id,
                    'invoice_no' => $lastInvoice->invoice_no,
                    'type' => $lastInvoice->type,
                    'lut_no' => $lastInvoice->lut_no,
                    'date' => $lastInvoice->date->format('Y-m-d'),
                    'client' => $lastInvoice->client?->name,
                    'total' => $lastInvoice->total,
                ] : null,
            ]);
        } catch (\Exception $e) {
            return ToolResult::json([
                'status' => 'error',
                'message' => 'Failed to generate cart invoice: '.$e->getMessage(),
            ]);
        }
    }

    protected function resolveAsset(string $type, string $domainName): mixed
    {
        return match ($type) {
            'domain' => Domain::where('tld', $domainName)->first(),
            'hosting' => Hosting::where('domain', $domainName)->first(),
            'email' => Email::where('domain', $domainName)->first(),
            default => null,
        };
    }

    /**
     * Assign the client to an unassigned asset. Returns a warning string,
     * an "error:..." string, or null.
     */
    protected function assignClient(mixed $model, string $domainName, mixed $clientIdInput): ?string
    {
        if ($clientIdInput === null) {
            return null;
        }

        if (! $model->client_id) {
            if (! Client::where('id', $clientIdInput)->exists()) {
                return "error:Client with ID {$clientIdInput} does not exist.";
            }
            $model->client_id = $clientIdInput;
            $model->save();

            return null;
        }

        return "Asset '{$domainName}' already has a client assigned (ID: {$model->client_id}). The provided client_id '{$clientIdInput}' was not saved.";
    }
}
