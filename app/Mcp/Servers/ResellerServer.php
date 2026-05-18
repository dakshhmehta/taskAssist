<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\ConvertToTaxInvoice;
use App\Mcp\Tools\CreateClient;
use App\Mcp\Tools\GenerateAssetInvoice;
use App\Mcp\Tools\GetInvoice;
use App\Mcp\Tools\GetNewAssetsWithoutInvoices;
use App\Mcp\Tools\GetResellerBalance;
use App\Mcp\Tools\GetUpcomingRenewals;
use App\Mcp\Tools\ListPendingProformas;
use App\Mcp\Tools\MarkInvoiceAsPaid;
use App\Mcp\Tools\SendInvoiceEmail;
use App\Mcp\Tools\UpdateInvoice;
use Laravel\Mcp\Server;

class ResellerServer extends Server
{
    public string $serverName = 'Reseller Server';

    public string $serverVersion = '0.7.0';

    public string $instructions = 'Example instructions for LLMs connecting to this MCP server.';

    public array $tools = [
        GetResellerBalance::class,
        GetUpcomingRenewals::class,
        GetInvoice::class,
        // UpdateInvoice::class,
        SendInvoiceEmail::class,
        GetNewAssetsWithoutInvoices::class,
        GenerateAssetInvoice::class,
        ListPendingProformas::class,
        ConvertToTaxInvoice::class,
        MarkInvoiceAsPaid::class,
        CreateClient::class,
    ];

    public array $resources = [
        // ExampleResource::class,
    ];

    public array $prompts = [
        // ExamplePrompt::class,
    ];
}
