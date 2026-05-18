<?php

namespace App\Mcp\Tools;

use App\Jobs\GenerateInvoice as GenerateInvoiceJob;
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
        return 'Generate an invoice for a specific domain, hosting, or email asset.';
    }

    /**
     * The input schema of the tool.
     */
    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        return $schema->string('domain', 'The domain name to generate the invoice for.')->required()
            ->string('type', 'The type of asset (domain, hosting, email). Defaults to domain if not specified.')
            ->string('invoice_date', 'Optional. The date for the invoice (Y-m-d). Defaults to today.')
            ->integer('client_id', 'Optional. The ID of the client to assign to the asset if not already assigned.');
    }

    /**
     * Execute the tool call.
     *
     * @return ToolResult|Generator
     */
    public function handle(array $arguments): ToolResult|Generator
    {
        $domainName = $arguments['domain'];
        $type = strtolower($arguments['type'] ?? 'domain');
        $invoiceDate = $arguments['invoice_date'] ?? null;
        $clientIdInput = $arguments['client_id'] ?? null;

        $model = null;

        if ($type === 'domain') {
            $model = Domain::where('tld', $domainName)->first();
        } elseif ($type === 'hosting') {
            $model = Hosting::where('domain', $domainName)->first();
        } elseif ($type === 'email') {
            $model = Email::where('domain', $domainName)->first();
        } else {
            return ToolResult::json([
                'status' => 'error',
                'message' => "Invalid type '{$type}'. Supported types are: domain, hosting, email.",
            ]);
        }

        if (!$model) {
            return ToolResult::json([
                'status' => 'error',
                'message' => "Asset of type '{$type}' with domain '{$domainName}' not found.",
            ]);
        }

        $warning = null;
        if ($clientIdInput !== null) {
            if (!$model->client_id) {
                // Verify that the client exists
                if (!\App\Models\Client::where('id', $clientIdInput)->exists()) {
                    return ToolResult::json([
                        'status' => 'error',
                        'message' => "Client with ID {$clientIdInput} does not exist.",
                    ]);
                }
                $model->client_id = $clientIdInput;
                $model->save();
            } else {
                $warning = "Asset '{$domainName}' already has a client assigned (ID: {$model->client_id}). The provided client_id '{$clientIdInput}' was not saved.";
            }
        }

        if (!$model->client_id) {
            return ToolResult::json([
                'status' => 'error',
                'message' => "Asset '{$domainName}' ({$type}) does not have a client assigned. Cannot generate invoice.",
            ]);
        }

        try {
            // If we adding domain, and has hosting, include hosting as well
            if ($type === 'domain') {
                $hosting = Hosting::where('domain', $domainName)->first();
                $model = [$model];
                if ($hosting) {
                    $model[] = $hosting;
                }

                // Check for email as well
                $email = Email::where('domain', $domainName)->first();
                if ($email) {
                    $model[] = $email;
                }
            }

            // Dispatch the job synchronously to get immediate results
            GenerateInvoiceJob::dispatchSync($model, $invoiceDate);

            // Fetch the newly created invoice for this asset
            $lastInvoice = $model->getLastInvoice();

            $response = [
                'status' => 'success',
                'message' => "Invoice generated successfully for '{$domainName}' ({$type})." . ($warning ? " Warning: {$warning}" : ""),
                'invoice' => $lastInvoice ? [
                    'id' => $lastInvoice->id,
                    'invoice_no' => $lastInvoice->invoice_no,
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
                'message' => "Failed to generate invoice: " . $e->getMessage(),
            ]);
        }
    }
}
