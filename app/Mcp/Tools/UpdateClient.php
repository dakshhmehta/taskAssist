<?php

namespace App\Mcp\Tools;

use App\Models\Client;
use Generator;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\Title;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;
use Ri\Accounting\Models\Account;

#[Title('Update Client')]
class UpdateClient extends Tool
{
    /**
     * A description of the tool.
     */
    public function description(): string
    {
        return 'Update an existing client\'s details (billing name, nickname, email, GSTIN, billing address). GSTIN and billing address are stored on the client\'s associated account. Locate the client with exactly one of: client ID, name, email, or GSTIN.';
    }

    /**
     * The input schema of the tool.
     */
    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        return $schema
            ->integer('client_id', 'The exact client ID. Use when a name/email/GSTIN search matches multiple clients.')
            ->string('name', 'Partial match on the client billing name or nickname.')
            ->string('email', 'Exact match on the client email address. Also sets the email when updating.')
            ->string('gstin', 'Exact match on the account GSTIN. Also sets the GSTIN when updating.')
            ->string('billing_name', 'The new billing name of the client.')
            ->string('nickname', 'The new nickname of the client.')
            ->string('new_email', 'The new email address of the client.')
            ->string('new_gstin', 'The new GSTIN of the client, stored on their Account.')
            ->string('address', 'The new billing address of the client, stored on their Account.');
    }

    /**
     * Execute the tool call.
     *
     * @return ToolResult|Generator
     */
    public function handle(array $arguments): ToolResult|Generator
    {
        $identifiers = array_filter([
            'client_id' => $arguments['client_id'] ?? null,
            'name' => $arguments['name'] ?? null,
            'email' => $arguments['email'] ?? null,
            'gstin' => $arguments['gstin'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        if (count($identifiers) === 0) {
            return ToolResult::json([
                'status' => 'error',
                'message' => 'Provide exactly one identifier: client_id, name, email, or gstin.',
            ]);
        }

        if (count($identifiers) > 1) {
            return ToolResult::json([
                'status' => 'error',
                'message' => 'Provide only one identifier, not multiple: ' . implode(', ', array_keys($identifiers)) . '.',
            ]);
        }

        $fields = array_filter([
            'billing_name' => $arguments['billing_name'] ?? null,
            'nickname' => $arguments['nickname'] ?? null,
            'email' => $arguments['new_email'] ?? null,
            'address' => $arguments['address'] ?? null,
            'gstin' => $arguments['new_gstin'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        if (count($fields) === 0) {
            return ToolResult::json([
                'status' => 'error',
                'message' => 'Provide at least one field to update: billing_name, nickname, new_email, address, or new_gstin.',
            ]);
        }

        // Perform email validation if provided
        if (isset($fields['email'])) {
            if (!filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) {
                return ToolResult::json([
                    'status' => 'error',
                    'message' => 'Invalid email address format.',
                ]);
            }
        }

        // Perform GSTIN validation if provided
        if (isset($fields['gstin'])) {
            $fields['gstin'] = strtoupper(trim($fields['gstin']));
            if (!preg_match('/^([0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1})$/i', $fields['gstin'])) {
                return ToolResult::json([
                    'status' => 'error',
                    'message' => 'Invalid GSTIN format. It must match the 15-character pattern: 22AAAAA1111A1Z1.',
                ]);
            }
        }

        $client = $this->locateClient($identifiers);

        if ($client === null) {
            return ToolResult::json([
                'status' => 'error',
                'message' => 'No client found matching the given identifier.',
            ]);
        }

        if ($client instanceof ToolResult) {
            return $client;
        }

        try {
            return DB::transaction(function () use ($client, $fields) {
                $warning = null;

                if (isset($fields['billing_name'])) {
                    $client->billing_name = $fields['billing_name'];
                }
                if (isset($fields['nickname'])) {
                    $client->nickname = $fields['nickname'];
                }
                if (isset($fields['email'])) {
                    $client->email = $fields['email'];
                }
                $client->save();

                $account = $client->account()->first();

                if (!$account) {
                    $client->syncWithLedger();
                    $account = $client->account()->first();
                }

                if ($account) {
                    if (isset($fields['billing_name'])) {
                        $account->billing_name = $fields['billing_name'];
                    }
                    if (isset($fields['address'])) {
                        $account->billing_address = $fields['address'];
                    }
                    if (isset($fields['gstin'])) {
                        $duplicate = Account::where('gstin', $fields['gstin'])
                            ->where('id', '!=', $account->id)
                            ->exists();

                        if ($duplicate) {
                            $warning = "GSTIN '{$fields['gstin']}' already exists on another account.";
                        }

                        $account->gstin = $fields['gstin'];
                    }
                    $account->save();
                }

                $response = [
                    'status' => 'success',
                    'message' => 'Client updated successfully.',
                    'client' => [
                        'id' => $client->id,
                        'billing_name' => $client->billing_name,
                        'nickname' => $client->nickname,
                        'email' => $client->email,
                        'account' => $account ? [
                            'id' => $account->id,
                            'name' => $account->name,
                            'billing_name' => $account->billing_name,
                            'billing_address' => $account->billing_address,
                            'gstin' => $account->gstin,
                        ] : null,
                    ],
                ];

                if ($warning !== null) {
                    $response['warning'] = $warning;
                }

                return ToolResult::json($response);
            });
        } catch (\Exception $e) {
            return ToolResult::json([
                'status' => 'error',
                'message' => 'Failed to update client: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Locate a single client from the given identifier.
     * Returns null when nothing matches, or a ToolResult error when ambiguous.
     */
    private function locateClient(array $identifiers): Client|ToolResult|null
    {
        if (isset($identifiers['client_id'])) {
            return Client::find($identifiers['client_id']);
        }

        if (isset($identifiers['email'])) {
            return Client::where('email', $identifiers['email'])->first();
        }

        if (isset($identifiers['gstin'])) {
            $account = Account::where('gstin', strtoupper(trim($identifiers['gstin'])))->first();

            if (!$account) {
                return null;
            }

            return Client::where('account_id', $account->id)->first();
        }

        $query = trim($identifiers['name']);

        $matches = Client::where('billing_name', 'like', "{$query}%")
            ->orWhere('nickname', 'like', "{$query}%")
            ->get();

        if ($matches->isEmpty()) {
            return null;
        }

        if ($matches->count() > 1) {
            return ToolResult::json([
                'status' => 'error',
                'message' => "Multiple clients match '{$query}'. Retry with the exact client_id: " . $matches->pluck('id')->implode(', ') . '.',
            ]);
        }

        return $matches->first();
    }
}
