<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\ConvertToTaxInvoice;
use App\Mcp\Tools\CreateClient;
use App\Mcp\Tools\FindClient;
use App\Mcp\Tools\GenerateAssetInvoice;
use App\Mcp\Tools\GenerateServiceInvoice;
use App\Mcp\Tools\GetInvoice;
use App\Mcp\Tools\GetNewAssetsWithoutInvoices;
use App\Mcp\Tools\GetResellerBalance;
use App\Mcp\Tools\GetUpcomingRenewals;
use App\Mcp\Tools\ListNotifications;
use App\Mcp\Tools\ListPendingProformas;
use App\Mcp\Tools\MarkInvoiceAsPaid;
use App\Mcp\Tools\Search;
use App\Mcp\Tools\SendInvoiceEmail;
use App\Mcp\Tools\UpdateClient;
use App\Mcp\Tools\UpdateInvoice;
use App\Mcp\Tools\VoidInvoice;
use Illuminate\Support\Facades\Auth;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\ServerContext;

class ResellerServer extends Server
{
    protected string $name = 'Reseller Server';

    protected string $version = '0.12.0';

    protected string $instructions = 'Example instructions for LLMs connecting to this MCP server.';

    protected array $tools = [
        Search::class,
        GetResellerBalance::class,
        GetUpcomingRenewals::class,
        GetInvoice::class,
        // UpdateInvoice::class,
        SendInvoiceEmail::class,
        GetNewAssetsWithoutInvoices::class,
        GenerateAssetInvoice::class,
        GenerateServiceInvoice::class,
        ListPendingProformas::class,
        ConvertToTaxInvoice::class,
        MarkInvoiceAsPaid::class,
        VoidInvoice::class,
        CreateClient::class,
        FindClient::class,
        UpdateClient::class,
        ListNotifications::class,
    ];

    protected array $resources = [
        // ExampleResource::class,
    ];

    protected array $prompts = [
        // ExamplePrompt::class,
    ];

    /**
     * The reseller server is admin-only: team members get no tools at all,
     * so nothing is listed by tools/list and every call fails as unknown.
     */
    public function createContext(): ServerContext
    {
        if (Auth::user()?->is_admin) {
            return parent::createContext();
        }

        $tools = $this->tools;

        try {
            $this->tools = [];

            return parent::createContext();
        } finally {
            $this->tools = $tools;
        }
    }
}
