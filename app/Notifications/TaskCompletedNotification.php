<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskCompletedNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected Task $task,
        protected ?User $actor,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(User $notifiable): array
    {
        $actorName = $this->actor?->name ?? 'A user';

        return array_merge(FilamentNotification::make()
            ->title($actorName.' completed '.$this->task->title)
            ->getDatabaseMessage(), [
                'event' => 'task_completed',
                'task_id' => $this->task->id,
                'actor_id' => $this->actor?->id,
            ]);
    }
}
