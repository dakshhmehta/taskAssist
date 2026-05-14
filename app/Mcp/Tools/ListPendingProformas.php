<?php

namespace App\Mcp\Tools;

use App\Models\Invoice;
use Carbon\Carbon;
use Generator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\Title;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;

#[Title('List Pending Proformas')]
class ListPendingProformas extends Tool
{
    /**
     * A description of the tool.
     */
    public function description(): string
    {
        return 'List all proforma invoices created this financial year that have not been converted to tax invoices.';
    }

    /**
     * The input schema of the tool.
     */
    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        return $schema->string('start_date', 'Optional. The start date to filter invoices from (format: Y-m-d). Defaults to the start of the current financial year.');
    }

    /**
     * Execute the tool call.
     *
     * @return ToolResult|Generator
     */
    public function handle(array $arguments): ToolResult|Generator
    {
        $now = now();
        
        if (isset($arguments['start_date'])) {
            $startDate = Carbon::parse($arguments['start_date'])->startOfDay();
        } else {
            // Financial year in India starts from April 1st
            $startDate = $now->month >= 4 
                ? Carbon::create($now->year, 4, 1)->startOfDay()
                : Carbon::create($now->year - 1, 4, 1)->startOfDay();
        }

        // Proforma invoices do not start with 'SI-' and do not have an associated tax invoice
        $invoices = Invoice::query()
            ->where('date', '>=', $startDate)
            ->where('invoice_no', 'NOT LIKE', 'SI-%')
            ->doesntHave('taxInvoice')
            ->with('client')
            ->orderBy('date', 'DESC')
            ->get();

        $results = $invoices->map(function ($invoice) {
            return [
                'id' => $invoice->id,
                'invoice_no' => $invoice->invoice_no,
                'date' => $invoice->date->format('Y-m-d'),
                'client' => $invoice->client?->name ?? 'Unknown',
                'total' => number_format($invoice->total, 2),
            ];
        });

        return ToolResult::json([
            'start_date' => $startDate->format('Y-m-d'),
            'count' => $results->count(),
            'invoices' => $results->values()->toArray(),
        ]);
    }
}
