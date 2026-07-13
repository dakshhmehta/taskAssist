<?php

namespace Tests\Feature;

use App\Mail\UpcomingRenewalsReminderMail;
use App\Models\Client;
use App\Models\Domain;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendUpcomingRenewalsReminderCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_the_upcoming_renewals_email_to_the_boss_email_recipient(): void
    {
        Mail::fake();

        config(['mail.boss_email' => 'boss@example.com']);

        $client = Client::create([
            'billing_name' => 'Test Client',
            'nickname' => 'Test',
        ]);

        Domain::create([
            'tld' => 'expired-domain.com',
            'expiry_date' => Carbon::parse('2026-06-16'),
            'client_id' => $client->id,
        ]);

        $this->artisan('renewals:send-upcoming-reminder', ['--date' => '2026-06-25'])
            ->expectsOutput('Sent upcoming renewals reminder to boss@example.com')
            ->expectsOutput('Expired items: 1')
            ->expectsOutput('Upcoming items: 0')
            ->assertSuccessful();

        Mail::assertSent(UpcomingRenewalsReminderMail::class, function (UpcomingRenewalsReminderMail $mail) {
            return $mail->hasTo('boss@example.com')
                && $mail->expiredRenewals->count() === 1
                && $mail->upcomingRenewals->count() === 0;
        });
    }

    public function test_it_fails_for_an_invalid_date(): void
    {
        Mail::fake();

        $this->artisan('renewals:send-upcoming-reminder', ['--date' => 'not-a-date'])
            ->expectsOutput('Invalid date format. Use Y-m-d.')
            ->assertFailed();

        Mail::assertNothingSent();
    }
}
