<?php

namespace App\Mcp\Tools;

use App\Models\Client;
use App\Models\Domain;
use App\Models\Email;
use App\Models\Hosting;
use Generator;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\Title;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;

#[Title('Find Client')]
class FindClient extends Tool
{
    /**
     * A description of the tool.
     */
    public function description(): string
    {
        return 'Search for a client in the registry by name (partial match), email (exact match), or associated domain name.';
    }

    /**
     * The input schema of the tool.
     */
    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        return $schema->string('query', 'The search term (name, email, or domain name).')->required();
    }

    /**
     * Execute the tool call.
     *
     * @return ToolResult|Generator
     */
    public function handle(array $arguments): ToolResult|Generator
    {
        $query = trim($arguments['query']);

        if ($query === '') {
            return ToolResult::json([
                'status' => 'error',
                'message' => 'Search query cannot be empty.',
            ]);
        }

        $clientIds = collect();

        // 1. Partial matches on name / nickname
        $nameMatches = Client::where('billing_name', 'like', "{$query}%")
            ->orWhere('nickname', 'like', "{$query}%")
            ->pluck('id');
        $clientIds = $clientIds->merge($nameMatches);

        // 2. Exact matches on email
        $emailMatches = Client::where('email', $query)->pluck('id');
        $clientIds = $clientIds->merge($emailMatches);

        // 3. Domain lookup across domains, hostings, and emails
        $domainClientIds = Domain::where('tld', $query)->whereNotNull('client_id')->pluck('client_id');
        $clientIds = $clientIds->merge($domainClientIds);

        $hostingClientIds = Hosting::where('domain', $query)->whereNotNull('client_id')->pluck('client_id');
        $clientIds = $clientIds->merge($hostingClientIds);

        $emailClientIds = Email::where('domain', $query)->whereNotNull('client_id')->pluck('client_id');
        $clientIds = $clientIds->merge($emailClientIds);

        // Fetch the unique clients with their accounts
        $uniqueClientIds = $clientIds->unique()->values()->all();

        if (empty($uniqueClientIds)) {
            return ToolResult::json([
                'status' => 'success',
                'message' => 'No clients found matching the query.',
                'clients' => [],
            ]);
        }

        $clients = Client::whereIn('id', $uniqueClientIds)
            ->with(['account'])
            ->get();

        $results = $clients->map(function ($client) {
            return [
                'id' => $client->id,
                'billing_name' => $client->billing_name,
                'nickname' => $client->nickname,
                'email' => $client->email,
                'receivable_amount' => $client->getReceivable(),
                'account' => $client->account ? [
                    'id' => $client->account->id,
                    'name' => $client->account->name,
                    'billing_name' => $client->account->billing_name,
                    'billing_address' => $client->account->billing_address,
                    'gstin' => $client->account->gstin,
                ] : null,
            ];
        });

        return ToolResult::json([
            'status' => 'success',
            'message' => 'Search completed successfully.',
            'clients' => $results->toArray(),
        ]);
    }
}
