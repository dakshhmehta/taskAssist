<?php

namespace Tests\Unit;

use App\Models\Client;
use App\Models\Domain;
use App\Models\Email;
use App\Models\Hosting;
use App\Services\UpcomingRenewalsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpcomingRenewalsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_expired_and_upcoming_domain_hosting_and_email_renewals_excluding_ignored_items()
    {
        Carbon::setTestNow(Carbon::parse('2026-06-25'));

        $client = Client::create([
            'billing_name' => 'Test Client',
            'nickname' => 'Test',
        ]);

        Domain::create([
            'tld' => 'expired-domain.com',
            'expiry_date' => Carbon::parse('2026-06-16'),
            'client_id' => $client->id,
        ]);

        Hosting::create([
            'domain' => 'upcoming-hosting.com',
            'expiry_date' => Carbon::parse('2026-07-01'),
            'client_id' => $client->id,
        ]);

        Email::create([
            'domain' => 'workspace.example.com',
            'provider' => 'Google Workspace',
            'expiry_date' => Carbon::parse('2026-07-05'),
            'accounts_count' => 3,
            'client_id' => $client->id,
        ]);

        Domain::create([
            'tld' => 'ignored-domain.com',
            'expiry_date' => Carbon::parse('2026-06-20'),
            'client_id' => $client->id,
            'ignored_at' => now(),
        ]);

        $renewals = app(UpcomingRenewalsService::class)->getRenewals(
            tillDate: Carbon::parse('2026-07-25')
        );

        $this->assertCount(3, $renewals);
        $this->assertSame('expired-domain.com', $renewals->first()['domain']);
        $this->assertTrue($renewals->first()['is_expired']);
        $this->assertSame('Hosting', $renewals->pluck('type')->get(1));
        $this->assertSame('GSuite', $renewals->pluck('type')->last());
        $this->assertSame(3, $renewals->last()['accounts']);
        $this->assertFalse($renewals->contains(fn (array $renewal) => $renewal['domain'] === 'ignored-domain.com'));

        Carbon::setTestNow();
    }
}
