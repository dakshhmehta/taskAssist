<?php

namespace Tests\Unit;

use App\Mcp\Servers\ResellerServer;
use App\Mcp\Tools\UpdateClient;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_updates_gstin_for_client_found_by_name(): void
    {
        $client = Client::create([
            'billing_name' => 'Kimaya Interiors',
            'nickname' => 'Kimaya',
            'email' => 'kimaya@example.com',
        ]);

        $result = (new UpdateClient)->handle([
            'name' => 'Kimaya Interiors',
            'new_gstin' => '24aajfu3158q1zj',
        ]);
        $response = $this->decode($result);

        $this->assertSame('success', $response['status']);
        $this->assertSame('24AAJFU3158Q1ZJ', $response['client']['account']['gstin']);
        $this->assertSame('24AAJFU3158Q1ZJ', $client->fresh()->account()->first()->gstin);
    }

    public function test_it_finds_clients_by_id_email_and_gstin(): void
    {
        $client = Client::create([
            'billing_name' => 'Lookup Corp',
            'email' => 'lookup@example.com',
        ]);
        $client->account()->first()->update(['gstin' => '22AAAAA1111A1Z1']);

        foreach ([
            ['client_id' => $client->id],
            ['email' => 'lookup@example.com'],
            ['gstin' => '22AAAAA1111A1Z1'],
        ] as $identifier) {
            $result = (new UpdateClient)->handle($identifier + ['nickname' => 'LC']);
            $response = $this->decode($result);

            $this->assertSame('success', $response['status'], json_encode($identifier));
            $this->assertSame($client->id, $response['client']['id']);
        }

        $this->assertSame('LC', $client->fresh()->nickname);
    }

    public function test_it_updates_billing_details(): void
    {
        $client = Client::create(['billing_name' => 'Old Name']);

        $result = (new UpdateClient)->handle([
            'client_id' => $client->id,
            'billing_name' => 'New Name',
            'new_email' => 'new@example.com',
            'address' => '42 New Street',
        ]);
        $response = $this->decode($result);

        $this->assertSame('success', $response['status']);
        $this->assertSame('New Name', $response['client']['billing_name']);
        $this->assertSame('new@example.com', $response['client']['email']);
        $this->assertSame('42 New Street', $response['client']['account']['billing_address']);

        $client->refresh();
        $this->assertSame('New Name', $client->billing_name);
        $this->assertSame('new@example.com', $client->email);
    }

    public function test_it_returns_error_when_client_is_not_found(): void
    {
        $result = (new UpdateClient)->handle([
            'name' => 'Nobody Here',
            'nickname' => 'Ghost',
        ]);
        $response = $this->decode($result);

        $this->assertSame('error', $response['status']);
    }

    public function test_it_returns_error_when_query_matches_multiple_clients(): void
    {
        Client::create(['billing_name' => 'Acme One']);
        Client::create(['billing_name' => 'Acme Two']);

        $result = (new UpdateClient)->handle([
            'name' => 'Acme',
            'nickname' => 'Ambiguous',
        ]);
        $response = $this->decode($result);

        $this->assertSame('error', $response['status']);
        $this->assertStringContainsString('client_id', strtolower($response['message']));
    }

    public function test_it_returns_error_for_invalid_gstin_and_email(): void
    {
        $client = Client::create(['billing_name' => 'Valid Corp']);

        $badGstin = $this->decode((new UpdateClient)->handle([
            'client_id' => $client->id,
            'new_gstin' => 'NOT-A-GSTIN',
        ]));
        $this->assertSame('error', $badGstin['status']);

        $badEmail = $this->decode((new UpdateClient)->handle([
            'client_id' => $client->id,
            'new_email' => 'not-an-email',
        ]));
        $this->assertSame('error', $badEmail['status']);
    }

    public function test_it_warns_but_updates_when_gstin_exists_on_another_account(): void
    {
        $owner = Client::create(['billing_name' => 'GSTIN Owner']);
        $owner->account()->first()->update(['gstin' => '22AAAAA1111A1Z1']);

        $client = Client::create(['billing_name' => 'GSTIN Borrower']);

        $result = (new UpdateClient)->handle([
            'client_id' => $client->id,
            'new_gstin' => '22AAAAA1111A1Z1',
        ]);
        $response = $this->decode($result);

        $this->assertSame('success', $response['status']);
        $this->assertArrayHasKey('warning', $response);
        $this->assertSame('22AAAAA1111A1Z1', $client->fresh()->account()->first()->gstin);
    }

    public function test_it_requires_an_identifier_and_at_least_one_field(): void
    {
        $client = Client::create(['billing_name' => 'Needy Corp']);

        $noIdentifier = $this->decode((new UpdateClient)->handle(['nickname' => 'X']));
        $this->assertSame('error', $noIdentifier['status']);

        $noFields = $this->decode((new UpdateClient)->handle(['client_id' => $client->id]));
        $this->assertSame('error', $noFields['status']);
    }

    public function test_it_is_registered_with_the_mcp_server(): void
    {
        $server = new ResellerServer;

        $this->assertContains(UpdateClient::class, $server->tools);
        $this->assertSame('0.11.0', $server->serverVersion);
    }

    private function decode($result): array
    {
        return json_decode($result->toArray()['content'][0]['text'], true);
    }
}
