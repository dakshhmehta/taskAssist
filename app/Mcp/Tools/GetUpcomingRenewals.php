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
        $today = now()->startOfDay();

        if ($tillDateStr) {
            $tillDate = Carbon::parse($tillDateStr)->endOfDay();
        } else {
            $tillDate = now()->addDays(7);
        }

        $renewals = [];

        // Fetch Domains
        $domainQuery = Domain::query()
            ->whereNotNull('expiry_date')
            ->excludeIgnored();

        if ($domainFilter) {
            $domainQuery->where('tld', 'like', "%{$domainFilter}%");
        } else {
            $domainQuery->where('expiry_date', '<=', $tillDate);
        }

        $domains = $domainQuery
            ->orderByRaw('CASE WHEN expiry_date < ? THEN 0 ELSE 1 END ASC', [$today->format('Y-m-d H:i:s')])
            ->orderBy('expiry_date', 'ASC')
            ->get();

        foreach ($domains as $domain) {
            $isExpired = $domain->expiry_date->lt($today);

            $renewals[] = [
                'type' => 'Domain',
                'domain' => $domain->tld,
                'expiry_date' => $domain->expiry_date->format('Y-m-d H:i:s'),
                'is_expired' => $isExpired,
                'days_overdue' => $isExpired ? $domain->expiry_date->diffInDays($today) : null,
                'days_until_expiry' => $isExpired ? null : $today->diffInDays($domain->expiry_date, false),
            ];
        }

        // Fetch GSuites (Emails)
        $emailQuery = Email::query()
            ->whereNotNull('expiry_date')
            ->excludeIgnored();

        if ($domainFilter) {
            $emailQuery->where('domain', 'like', "%{$domainFilter}%");
        } else {
            $emailQuery->where('expiry_date', '<=', $tillDate);
        }

        $emails = $emailQuery
            ->orderByRaw('CASE WHEN expiry_date < ? THEN 0 ELSE 1 END ASC', [$today->format('Y-m-d H:i:s')])
            ->orderBy('expiry_date', 'ASC')
            ->get();

        foreach ($emails as $email) {
            $isExpired = $email->expiry_date->lt($today);

            $renewals[] = [
                'type' => 'GSuite',
                'domain' => $email->domain,
                'expiry_date' => $email->expiry_date->format('Y-m-d H:i:s'),
                'accounts' => $email->accounts_count ?? 'Unknown',
                'is_expired' => $isExpired,
                'days_overdue' => $isExpired ? $email->expiry_date->diffInDays($today) : null,
                'days_until_expiry' => $isExpired ? null : $today->diffInDays($email->expiry_date, false),
            ];
        }

        return ToolResult::json([
            'renewals' => $renewals,
        ]);
    }
}
