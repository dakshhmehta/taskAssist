<?php

namespace App\Mcp\Tools;

use App\Models\Client;
use Generator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Ri\Accounting\Models\Account;

class CreateClient extends Tool
{
    protected string $title = 'Create Client';

    /**
     * A description of the tool.
     */
    public function description(): string
    {
        return 'Create a new client with billing name, optional nickname, email, GSTIN, and billing address. GSTIN and billing address are updated under the client\'s associated account.';
    }

    /**
     * The input schema of the tool.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'billing_name' => $schema->string()->description('The billing name of the client.')->required(),
            'nickname' => $schema->string()->description('An optional nickname for the client.'),
            'email' => $schema->string()->description('The email address of the client.'),
            'gstin' => $schema->string()->description('The GSTIN of the client, updated under their Account.'),
            'address' => $schema->string()->description('The billing address of the client, updated under their Account.'),
        ];
    }

    /**
     * Execute the tool call.
     */
    public function handle(Request $request): Response|Generator
    {
        $billingName = $request->get('billing_name');
        $nickname = $request->get('nickname') ?? null;
        $email = $request->get('email') ?? null;
        $address = $request->get('address') ?? null;
        $gstin = $request->get('gstin') ?? null;

        // Perform email validation if provided
        if ($email !== null && $email !== '') {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return Response::json([
                    'status' => 'error',
                    'message' => 'Invalid email address format.',
                ]);
            }
        }

        // Perform GSTIN validation if provided
        if ($gstin !== null && $gstin !== '') {
            $gstin = strtoupper(trim($gstin));
            if (! preg_match('/^([0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1})$/i', $gstin)) {
                return Response::json([
                    'status' => 'error',
                    'message' => 'Invalid GSTIN format. It must match the 15-character pattern: 22AAAAA1111A1Z1.',
                ]);
            }

            // Check unique constraint for GSTIN in accounts table
            if (Account::where('gstin', $gstin)->exists()) {
                return Response::json([
                    'status' => 'error',
                    'message' => "An account with the GSTIN '{$gstin}' already exists.",
                ]);
            }
        }

        try {
            return DB::transaction(function () use ($billingName, $nickname, $email, $address, $gstin) {
                $existing = Client::where('billing_name', $billingName)->first();

                if ($existing) {
                    if ($nickname !== null) {
                        $existing->nickname = $nickname;
                    }
                    if ($email !== null) {
                        $existing->email = $email;
                    }
                    $existing->save();

                    $account = $existing->account()->first();
                    if ($account) {
                        $account->billing_name = $billingName;
                        if ($address !== null) {
                            $account->billing_address = $address;
                        }
                        if ($gstin !== null) {
                            $account->gstin = $gstin;
                        }
                        $account->save();
                    }

                    return Response::json([
                        'status' => 'success',
                        'message' => 'Client already exists. Updated existing record.',
                        'client' => [
                            'id' => $existing->id,
                            'billing_name' => $existing->billing_name,
                            'nickname' => $existing->nickname,
                            'email' => $existing->email,
                            'account' => $account ? [
                                'id' => $account->id,
                                'name' => $account->name,
                                'billing_name' => $account->billing_name,
                                'billing_address' => $account->billing_address,
                                'gstin' => $account->gstin,
                            ] : null,
                        ],
                    ]);
                } else {
                    $client = Client::create([
                        'billing_name' => $billingName,
                        'nickname' => $nickname,
                        'email' => $email,
                    ]);

                    // 2. Fetch the newly created account.
                    $account = $client->account()->first();

                    if (! $account) {
                        // Fallback to manual sync if for any reason it wasn't triggered automatically
                        $client->syncWithLedger();
                        $account = $client->account()->first();
                    }

                    if ($account) {
                        $account->billing_name = $billingName;
                        if ($address !== null) {
                            $account->billing_address = $address;
                        }
                        if ($gstin !== null) {
                            $account->gstin = $gstin;
                        }
                        $account->save();
                    }

                    return Response::json([
                        'status' => 'success',
                        'message' => 'Client and its associated account created successfully.',
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
                    ]);
                }
            });
        } catch (\Exception $e) {
            return Response::json([
                'status' => 'error',
                'message' => 'Failed to create client: '.$e->getMessage(),
            ]);
        }
    }
}
