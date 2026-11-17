<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\UserLeave;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LeaveStatusChangedNotification extends Notification
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

        $body = $leave->code.': '.$leave->from_date->format(config('app.date_format')).' to '.$leave->to_date->format(config('app.date_format'));

        if ($leave->admin_remarks) {
            $body .= ' — '.$leave->admin_remarks;
        }

        return array_merge(FilamentNotification::make()
            ->title('Your leave request was '.strtolower($leave->status))
            ->body($body)
            ->getDatabaseMessage(), [
                'event' => 'leave_status_changed',
                'leave_id' => $leave->id,
                'status' => $leave->status,
                'user_id' => $leave->user_id,
                'actor_id' => $leave->approved_by_user_id,
            ]);
    }
}
