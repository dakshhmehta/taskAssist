<?php

namespace App\Mcp\Tools;

use App\Services\UpcomingRenewalsService;
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
        return 'Get the upcoming domain, hosting, and email (GSuite) renewals.';
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
            $tillDate = now()->addDays(7);
        }

        $renewals = app(UpcomingRenewalsService::class)
            ->getRenewals($domainFilter, $tillDate)
            ->all();

        return ToolResult::json([
            'renewals' => $renewals,
        ]);
    }
}
