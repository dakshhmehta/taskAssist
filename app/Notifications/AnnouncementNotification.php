<?php

namespace App\Notifications;

use App\Mail\AnnouncementEmail;
use App\Models\Announcement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Mail;

class AnnouncementNotification extends Notification
{
    use Queueable;

    public function __construct(public Announcement $announcement)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'announcement_id' => $this->announcement->id,
            'title' => $this->announcement->title,
            'body' => $this->announcement->body,
            'created_by' => $this->announcement->user->name,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Announcement: ' . $this->announcement->title)
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line($this->announcement->body)
            ->line('Posted by: ' . $this->announcement->user->name)
            ->salutation('Thanks, ' . config('app.name'));
    }

    public static function emailAll(Announcement $announcement): void
    {
        $users = \App\Models\User::where(function ($q) {
            $q->where('is_disabled', false)->orWhereNull('is_disabled');
        })->get();

        foreach ($users as $user) {
            Mail::to($user)->queue(new AnnouncementEmail($announcement));
        }

        $announcement->update(['is_emailed' => true, 'emailed_at' => now()]);
    }

    public static function notifyAll(Announcement $announcement): void
    {
        $users = \App\Models\User::where(function ($q) {
            $q->where('is_disabled', false)->orWhereNull('is_disabled');
        })->get();

        \Illuminate\Support\Facades\Notification::send($users, new static($announcement));
    }
}
