<?php

namespace App\Notifications;

use App\Models\Site;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramMessage;

class SiteIsUpNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(Site $notifiable): array
    {
        return ['telegram'];
    }

    public function toTelegram(Site $site)
    {
        return TelegramMessage::create()
            ->to($site->routeNotificationForTelegram())
            ->line($site->domain . ' is UP again');
    }
}
