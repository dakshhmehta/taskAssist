<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DailyTaskSummaryMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user, $createdTasks, $completedTasks;

    public function __construct($user, $createdTasks, $completedTasks)
    {
        $this->user = $user;
        $this->createdTasks = $createdTasks;
        $this->completedTasks = $completedTasks;
    }

    public function build()
    {
        return $this->subject('Your Task Summary for Today - '.now()->format('d-m-Y'))
            ->markdown('emails.daily_summary');
    }
}
