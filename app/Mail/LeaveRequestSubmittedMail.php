<?php

namespace App\Mail;

use App\Models\UserLeave;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LeaveRequestSubmittedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public UserLeave $leave)
    {
    }

    public function build()
    {
        return $this->subject('New Leave Request: ' . $this->leave->user->name)
            ->markdown('emails.leave_request_submitted');
    }
}
