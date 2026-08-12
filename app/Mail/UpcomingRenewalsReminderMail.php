<?php

namespace App\Mail;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class UpcomingRenewalsReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Carbon $referenceDate,
        public int $windowDays,
        public Collection $expiredRenewals,
        public Collection $upcomingRenewals,
    ) {
    }

    public function build()
    {
        $expiredCount = $this->expiredRenewals->count();

        return $this->subject(
            $expiredCount > 0
                ? "URGENT: {$expiredCount} Renewal" . ($expiredCount === 1 ? ' is' : 's are') . " Overdue — Action Required"
                : "Upcoming Renewals Check ({$this->windowDays}-day window) - {$this->referenceDate->format('d-m-Y')}"
        )
            ->markdown('emails.upcoming_renewals');
    }
}
