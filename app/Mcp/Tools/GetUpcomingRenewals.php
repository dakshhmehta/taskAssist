<?php

namespace App\Mcp\Tools;

use App\Services\UpcomingRenewalsService;
use Carbon\Carbon;
use Generator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class GetUpcomingRenewals extends Tool
{
    protected string $title = 'Get Upcoming Renewals';

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
    public function schema(JsonSchema $schema): array
    {
        return [
            'domain' => $schema->string()->description('Optional. The domain name to filter by.'),
            'tillDate' => $schema->string()->description('Optional. The date to filter renewals until (format: Y-m-d). Defaults to the end of the current week.'),
        ];
    }

    /**
     * Execute the tool call.
     */
    public function handle(Request $request): Response|Generator
    {
        $domainFilter = $request->get('domain') ?? null;
        $tillDateStr = $request->get('tillDate') ?? null;

        if ($tillDateStr) {
            $tillDate = Carbon::parse($tillDateStr)->endOfDay();
        } else {
            $tillDate = now()->addDays(7);
        }

        $renewals = app(UpcomingRenewalsService::class)
            ->getRenewals($domainFilter, $tillDate)
            ->all();

        return Response::json([
            'renewals' => $renewals,
        ]);
    }
}
