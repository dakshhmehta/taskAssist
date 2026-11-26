<?php

namespace Tests\Unit;

use App\Mcp\Servers\ResellerServer;
use App\Mcp\Tools\Search;
use App\Models\Client;
use App\Models\Domain;
use App\Models\Email;
use App\Models\Hosting;
use App\Models\Invoice;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Mcp\Request;
use Tests\TestCase;

class SearchToolTest extends TestCase
{
    use RefreshDatabase;

    private function search(string $query): array
    {
        return json_decode((string) (new Search)->handle(new Request(['query' => $query]))->content(), true);
    }

    private function makeAcmeRecords(): void
    {
        $client = Client::create([
            'billing_name' => 'Acme Interiors',
            'nickname' => 'Acme',
            'email' => 'acme@example.com',
        ]);

        Domain::create(['tld' => 'acme.com', 'client_id' => $client->id]);
        Hosting::create(['domain' => 'acme.com', 'server' => 'srv1', 'client_id' => $client->id]);
        Email::create(['domain' => 'acme.com', 'provider' => 'Google Workspace', 'accounts_count' => 2, 'client_id' => $client->id]);
        Invoice::create([
            'invoice_no' => 'PI-ACME-001',
            'client_id' => $client->id,
            'date' => now(),
        ]);

        $user = User::factory()->create();
        Task::create(['title' => 'Acme renewal call', 'assignee_id' => $user->id]);
    }

    public function test_it_returns_every_record_type_in_one_flat_result_set(): void
    {
        $this->makeAcmeRecords();

        $payload = $this->search('acme');

        $this->assertSame('success', $payload['status']);
        $this->assertSame(
            ['client', 'domain', 'email', 'hosting', 'invoice', 'task'],
            collect($payload['results'])->pluck('type')->sort()->values()->all()
        );

        foreach ($payload['results'] as $result) {
            $this->assertArrayHasKey('id', $result);
            $this->assertArrayHasKey('title', $result);
            $this->assertArrayHasKey('details', $result);
            $this->assertIsArray($result['details']);
        }

        $client = collect($payload['results'])->firstWhere('type', 'client');
        $this->assertSame('Acme Interiors', $client['title']);
        $this->assertSame('Acme', $client['details']['Nickname']);

        $hosting = collect($payload['results'])->firstWhere('type', 'hosting');
        $this->assertSame('srv1', $hosting['details']['Server']);

        $email = collect($payload['results'])->firstWhere('type', 'email');
        $this->assertSame(2, $email['details']['Accounts']);
    }

    public function test_every_word_must_match_like_filament_global_search(): void
    {
        $this->makeAcmeRecords();

        $payload = $this->search('acme interiors');

        $this->assertSame('success', $payload['status']);
        // Invoice matches too: its client is "Acme Interiors" (relation attribute, like Filament).
        $this->assertSame(['client', 'invoice'], collect($payload['results'])->pluck('type')->unique()->sort()->values()->all());
        $this->assertSame('Acme Interiors', collect($payload['results'])->firstWhere('type', 'client')['title']);
    }

    public function test_clients_are_matched_on_nickname_and_email_too(): void
    {
        $this->makeAcmeRecords();

        $this->assertSame('Acme Interiors', $this->search('acme@example.com')['results'][0]['title']);
    }

    public function test_a_blank_query_returns_an_error(): void
    {
        $payload = $this->search('   ');

        $this->assertSame('error', $payload['status']);
        $this->assertArrayNotHasKey('results', $payload);
    }

    public function test_no_matches_returns_an_empty_successful_result_set(): void
    {
        $this->makeAcmeRecords();

        $payload = $this->search('nothing-matches-this');

        $this->assertSame('success', $payload['status']);
        $this->assertSame([], $payload['results']);
    }

    public function test_each_type_is_capped_at_fifty_results(): void
    {
        for ($i = 1; $i <= 55; $i++) {
            Client::create(['billing_name' => sprintf('Bulk Client %02d', $i)]);
        }

        $payload = $this->search('bulk');

        $this->assertCount(55, Client::where('billing_name', 'like', '%bulk%')->get());
        $this->assertCount(50, collect($payload['results'])->where('type', 'client'));
    }

    public function test_the_tool_is_registered_on_the_reseller_server_only(): void
    {
        $this->assertContains(Search::class, $this->mcpServerToolClasses(ResellerServer::class));
        $this->assertNotContains(Search::class, $this->mcpServerToolClasses(\App\Mcp\Servers\TaskServer::class));
    }
}
