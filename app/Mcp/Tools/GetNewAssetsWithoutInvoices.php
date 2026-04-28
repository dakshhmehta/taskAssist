<?php

namespace App\Mcp\Tools;

use App\Models\Domain;
use App\Models\Email;
use Generator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\Title;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;

#[Title('Get New Assets Without Invoices')]
class GetNewAssetsWithoutInvoices extends Tool
{
    public function description(): string
    {
        return 'Get newly added domains and Workspace accounts that have no invoice history yet.';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        $schema->integer('days', 'Optional. Look back this many days for newly added records. Defaults to 30 days.');
        $schema->string('domain', 'Optional. Filter by domain name.');

        return $schema;
    }

    public function handle(array $arguments): ToolResult|Generator
    {
        $days = max(1, (int) ($arguments['days'] ?? 30));
        $domainFilter = $arguments['domain'] ?? null;
        $since = now()->subDays($days)->startOfDay();

        $items = [];

        $domainQuery = Domain::query()
            ->where('created_at', '>=', $since)
            ->whereDoesntHave('invoiceItems')
            ->excludeIgnored()
            ->orderBy('created_at', 'DESC');

        if ($domainFilter) {
            $domainQuery->where('tld', 'like', "%{$domainFilter}%");
        }

        foreach ($domainQuery->get() as $domain) {
            $items[] = [
                'type' => 'Domain',
                'domain' => $domain->tld,
                'created_at' => optional($domain->created_at)?->format('Y-m-d H:i:s'),
                'expiry_date' => optional($domain->expiry_date)?->format('Y-m-d H:i:s'),
                'client_id' => $domain->client_id,
                'has_client' => $domain->client_id !== null,
                'invoice_count' => $domain->invoices()->count(),
            ];
        }

        $emailQuery = Email::query()
            ->where('created_at', '>=', $since)
            ->whereDoesntHave('invoiceItems')
            ->excludeIgnored()
            ->orderBy('created_at', 'DESC');

        if ($domainFilter) {
            $emailQuery->where('domain', 'like', "%{$domainFilter}%");
        }

        foreach ($emailQuery->get() as $email) {
            $items[] = [
                'type' => 'Workspace',
                'domain' => $email->domain,
                'accounts' => $email->accounts_count ?? 'Unknown',
                'created_at' => optional($email->created_at)?->format('Y-m-d H:i:s'),
                'expiry_date' => optional($email->expiry_date)?->format('Y-m-d H:i:s'),
                'client_id' => $email->client_id,
                'has_client' => $email->client_id !== null,
                'invoice_count' => $email->invoices()->count(),
            ];
        }

        usort($items, function (array $left, array $right) {
            return strcmp($right['created_at'] ?? '', $left['created_at'] ?? '');
        });

        return ToolResult::json([
            'since' => $since->format('Y-m-d H:i:s'),
            'count' => count($items),
            'items' => $items,
        ]);
    }
}
