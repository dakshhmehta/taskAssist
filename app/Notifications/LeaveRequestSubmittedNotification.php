<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\UserLeave;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LeaveRequestSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(protected UserLeave $leave) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(User $notifiable): array
    {
        $leave = $this->leave;

        return array_merge(FilamentNotification::make()
            ->title($leave->user->name.' requested leave')
            ->body($leave->code.': '.$leave->from_date->format(config('app.date_format')).' to '.$leave->to_date->format(config('app.date_format')).' ('.$leave->leave_days.' day'.($leave->leave_days > 1 ? 's' : '').')')
            ->getDatabaseMessage(), [
                'event' => 'leave_request_submitted',
                'leave_id' => $leave->id,
                'user_id' => $leave->user_id,
                'actor_id' => $leave->user_id,
            ]);
    }
}
