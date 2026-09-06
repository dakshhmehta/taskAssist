<?php

namespace App\Mail;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class PaymentRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Carbon $period,
        public Collection $paymentRequests,
    ) {}

    public function build()
    {
        return $this->subject('Payment Request for '.$this->period->format('F Y'))
            ->markdown('emails.payment_request');
    }
}
