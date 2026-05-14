<?php

namespace App\Mcp\Tools;

use App\Jobs\GenerateInvoice;
use App\Models\Domain;
use Generator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\Title;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;

#[Title('Generate Domain Invoice')]
class GenerateDomainInvoice extends Tool
{
    /**
     * A description of the tool.
     */
    public function description(): string
    {
        return 'Generate an invoice for a specific domain.';
    }

    /**
     * The input schema of the tool.
     */
    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        return $schema->string('domain', 'The domain name (tld) to generate the invoice for.')->required()
            ->string('invoice_date', 'Optional. The date for the invoice (Y-m-d). Defaults to today.');
    }

    /**
     * Execute the tool call.
     *
     * @return ToolResult|Generator
     */
    public function handle(array $arguments): ToolResult|Generator
    {
        $domainName = $arguments['domain'];
        $invoiceDate = $arguments['invoice_date'] ?? null;

        // Find the domain by TLD
        $domain = Domain::where('tld', $domainName)->first();

        if (!$domain) {
            return ToolResult::json([
                'status' => 'error',
                'message' => "Domain '{$domainName}' not found.",
            ]);
        }

        if (!$domain->client_id) {
            return ToolResult::json([
                'status' => 'error',
                'message' => "Domain '{$domainName}' does not have a client assigned. Cannot generate invoice.",
            ]);
        }

        try {
            // Dispatch the job synchronously to get immediate results
            GenerateInvoice::dispatchSync([$domain], $invoiceDate);

            // Fetch the newly created invoice for this domain
            $lastInvoice = $domain->getLastInvoice();

            return ToolResult::json([
                'status' => 'success',
                'message' => "Invoice generated successfully for '{$domainName}'.",
                'invoice' => $lastInvoice ? [
                    'id' => $lastInvoice->id,
                    'invoice_no' => $lastInvoice->invoice_no,
                    'date' => $lastInvoice->date->format('Y-m-d'),
                    'client' => $lastInvoice->client?->name,
                    'total' => $lastInvoice->total,
                ] : null,
            ]);
        } catch (\Exception $e) {
            return ToolResult::json([
                'status' => 'error',
                'message' => "Failed to generate invoice: " . $e->getMessage(),
            ]);
        }
    }
}
