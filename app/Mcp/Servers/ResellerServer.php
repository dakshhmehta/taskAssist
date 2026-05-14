<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\GenerateDomainInvoice;
use App\Mcp\Tools\GetNewAssetsWithoutInvoices;
use App\Mcp\Tools\GetResellerBalance;
use App\Mcp\Tools\GetUpcomingRenewals;
use Laravel\Mcp\Server;

class ResellerServer extends Server
{
    public string $serverName = 'Reseller Server';

    public string $serverVersion = '0.2.0';

    public string $instructions = 'Example instructions for LLMs connecting to this MCP server.';

    public array $tools = [
        GetResellerBalance::class,
        GetUpcomingRenewals::class,
        GetNewAssetsWithoutInvoices::class,
        GenerateDomainInvoice::class,
    ];

    public array $resources = [
        // ExampleResource::class,
    ];

    public array $prompts = [
        // ExamplePrompt::class,
    ];
}
