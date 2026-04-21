<?php

namespace App\Mcp\Tools;

use App\ResellerClub;
use Generator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\Title;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;

#[Title('Get Reseller Balance')]
class GetResellerBalance extends Tool
{
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
    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        return $schema;
    }

    /**
     * Execute the tool call.
     *
     * @return ToolResult|Generator
     */
    public function handle(array $arguments): ToolResult|Generator
    {
        $balance = ResellerClub::getBalance();

        return ToolResult::json([
            'balance' => $balance,
        ]);
    }
}
