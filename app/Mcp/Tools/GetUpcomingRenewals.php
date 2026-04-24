<?php

namespace App\Mcp\Tools;

use App\Models\Domain;
use App\Models\Email;
use Carbon\Carbon;
use Generator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\Title;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;

#[Title('Get Upcoming Renewals')]
class GetUpcomingRenewals extends Tool
{
    /**
     * A description of the tool.
     */
    public function description(): string
    {
        return 'Get the upcoming domain and email (GSuite) renewals.';
    }

    /**
     * The input schema of the tool.
     */
    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        $schema->string('domain', 'Optional. The domain name to filter by.');
        $schema->string('tillDate', 'Optional. The date to filter renewals until (format: Y-m-d). Defaults to the end of the current week.');

        return $schema;
    }

    /**
     * Execute the tool call.
     *
     * @return ToolResult|Generator
     */
    public function handle(array $arguments): ToolResult|Generator
    {
        $domainFilter = $arguments['domain'] ?? null;
        $tillDateStr = $arguments['tillDate'] ?? null;

        if ($tillDateStr) {
            $tillDate = Carbon::parse($tillDateStr)->endOfDay();
        } else {
            $tillDate = now()->addDays(5);
        }

        $renewals = [];

        // Fetch Domains
        $domainQuery = Domain::query()
            ->where('expiry_date', '>=', now())
            ->excludeIgnored();

        if ($domainFilter) {
            $domainQuery->where('tld', 'like', "%{$domainFilter}%");
        } else {
            $domainQuery->where('expiry_date', '<=', $tillDate);
        }

        foreach ($domainQuery->get() as $domain) {
            $renewals[] = [
                'type' => 'Domain',
                'domain' => $domain->tld,
                'expiry_date' => $domain->expiry_date->format('Y-m-d H:i:s'),
            ];
        }

        // Fetch GSuites (Emails)
        $emailQuery = Email::query()
            ->where('expiry_date', '>=', now())
            ->excludeIgnored();

        if ($domainFilter) {
            $emailQuery->where('domain', 'like', "%{$domainFilter}%");
        } else {
            $emailQuery->where('expiry_date', '<=', $tillDate);
        }

        foreach ($emailQuery->get() as $email) {
            $renewals[] = [
                'type' => 'GSuite',
                'domain' => $email->domain,
                'expiry_date' => $email->expiry_date->format('Y-m-d H:i:s'),
                'accounts' => $email->accounts_count ?? 'Unknown',
            ];
        }

        return ToolResult::json([
            'renewals' => $renewals,
        ]);
    }
}
