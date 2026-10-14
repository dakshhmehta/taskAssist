<?php

namespace App\Mcp\Tools;

use App\Models\Client;
use App\Models\Domain;
use App\Models\Email;
use App\Models\Hosting;
use Generator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class FindClient extends Tool
{
    protected string $title = 'Find Client';

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
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('The search term (name, email, or domain name).')->required(),
        ];
    }

    /**
     * Execute the tool call.
     */
    public function handle(Request $request): Response|Generator
    {
        $query = trim($request->get('query'));

        if ($query === '') {
            return Response::json([
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
            return Response::json([
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

        return Response::json([
            'status' => 'success',
            'message' => 'Search completed successfully.',
            'clients' => $results->toArray(),
        ]);
    }
}
