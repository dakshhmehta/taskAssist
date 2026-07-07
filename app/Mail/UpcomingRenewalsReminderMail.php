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
        return $this->subject('Upcoming Renewals Check (' . $this->windowDays . '-day window) - ' . $this->referenceDate->format('d-m-Y'))
            ->markdown('emails.upcoming_renewals');
    }
}
