<?php

namespace App\Console\Commands;

use App\Mail\UpcomingRenewalsReminderMail;
use App\Services\UpcomingRenewalsService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendUpcomingRenewalsReminderCommand extends Command
{
    protected $signature = 'renewals:send-upcoming-reminder {--date= : The reference date for the reminder (Y-m-d)}';

    protected $description = 'Send the upcoming renewals reminder email';

    public function handle(UpcomingRenewalsService $upcomingRenewalsService): int
    {
        $recipient = config('mail.boss_email');

        try {
            $referenceDate = $this->option('date')
                ? Carbon::parse($this->option('date'))->startOfDay()
                : now()->startOfDay();
        } catch (\Exception $exception) {
            $this->error('Invalid date format. Use Y-m-d.');

            return self::FAILURE;
        }

        Carbon::setTestNow($referenceDate);

        try {
            $renewals = $upcomingRenewalsService->getRenewals(
                tillDate: $referenceDate->copy()->addDays(30)->endOfDay(),
            );
        } finally {
            Carbon::setTestNow();
        }

        $expiredRenewals = $renewals->where('is_expired', true)->values();
        $upcomingRenewals = $renewals->where('is_expired', false)->values();

        Mail::to($recipient)->send(
            new UpcomingRenewalsReminderMail(
                referenceDate: $referenceDate,
                windowDays: 30,
                expiredRenewals: $expiredRenewals,
                upcomingRenewals: $upcomingRenewals,
            )
        );

        $this->info("Sent upcoming renewals reminder to {$recipient}");
        $this->info('Expired items: ' . $expiredRenewals->count());
        $this->info('Upcoming items: ' . $upcomingRenewals->count());

        return self::SUCCESS;
    }
}
