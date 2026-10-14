<?php

namespace App\Mcp\Tools;

use App\ResellerClub;
use Generator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class GetResellerBalance extends Tool
{
    protected string $title = 'Get Reseller Balance';

    /**
     * A description of the tool.
     */
    public function description(): string
    {
        return 'Get the most recent balance from resellerclub account';
    }

    /**
     * The input schema of the tool.
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    /**
     * Execute the tool call.
     */
    public function handle(Request $request): Response|Generator
    {
        $balance = ResellerClub::getBalance();

        return Response::json([
            'balance' => $balance,
        ]);
    }
}
