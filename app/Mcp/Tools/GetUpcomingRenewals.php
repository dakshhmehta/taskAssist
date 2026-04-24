<?php

namespace App\Mcp\Tools;

use App\ResellerClub;
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
            $tillDate = now()->endOfWeek();
        }

        $allDomains = ResellerClub::getDomains('expiring');
        $allGSuites = ResellerClub::getGSuites();

        $renewals = [];

        if (is_array($allDomains)) {
            foreach ($allDomains as $key => $domain) {
                if (!is_numeric($key)) {
                    continue; // Skip pagination keys like 'recsonpage', 'recsindb'
                }

                $expiryDate = Carbon::createFromTimestamp($domain['entity.endtime']);

                if ($expiryDate->lte($tillDate)) {
                    if ($domainFilter && stripos($domain['entity.description'], $domainFilter) === false) {
                        continue;
                    }

                    $renewals[] = [
                        'type' => 'Domain',
                        'domain' => $domain['entity.description'],
                        'expiry_date' => $expiryDate->format('Y-m-d H:i:s'),
                    ];
                }
            }
        }

        if (is_array($allGSuites)) {
            foreach ($allGSuites as $key => $gsuite) {
                if (!is_numeric($key)) {
                    continue;
                }

                $expiryDate = Carbon::createFromTimestamp($gsuite['entity.endtime']);

                if ($expiryDate->lte($tillDate)) {
                    if ($domainFilter && stripos($gsuite['entity.description'], $domainFilter) === false) {
                        continue;
                    }

                    $renewals[] = [
                        'type' => 'GSuite',
                        'domain' => $gsuite['entity.description'],
                        'expiry_date' => $expiryDate->format('Y-m-d H:i:s'),
                        'accounts' => $gsuite['accounts_count'] ?? 'Unknown',
                    ];
                }
            }
        }

        return ToolResult::json([
            'renewals' => $renewals,
        ]);
    }
}
