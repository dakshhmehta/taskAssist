<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DailyTaskSummaryMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user, $createdTasks, $completedTasks, $date;

    public function __construct($date, $user, $createdTasks, $completedTasks)
    {
        $this->date = $date;
        $this->user = $user;
        $this->createdTasks = $createdTasks;
        $this->completedTasks = $completedTasks;
    }

    public function build()
    {
        return $this->subject('Your Task Summary for Today - ' . $this->date->format('d-m-Y'))
            ->markdown('emails.daily_summary');
    }
}
