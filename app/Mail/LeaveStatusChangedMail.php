<?php

namespace App\Mail;

use App\Models\UserLeave;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LeaveStatusChangedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public UserLeave $leave)
    {
    }

    public function build()
    {
        return $this->subject('Your Leave Request has been ' . ucfirst(strtolower($this->leave->status)))
            ->markdown('emails.leave_status_changed');
    }
}
