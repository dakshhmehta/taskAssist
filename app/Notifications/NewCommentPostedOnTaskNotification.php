<?php

namespace App\Notifications;

use App\Models\Comment;
use App\Models\Task;
use App\Models\User;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewCommentPostedOnTaskNotification extends Notification
{
    use Queueable;

    public $comment = null;

    /**
     * Create a new notification instance.
     */
    public function __construct(Comment $comment)
    {
        $this->comment = $comment;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(User $notifiable): array
    {
        /** @var Task $task */
        $task = $this->comment->subject;

        return array_merge(FilamentNotification::make()
            ->title($this->comment->user->name.' commented on '.$task->title)
            ->body($this->comment->comment)
            ->getDatabaseMessage(), [
                'event' => 'task_commented',
                'task_id' => $task->id,
                'actor_id' => $this->comment->user_id,
                'comment_id' => $this->comment->id,
            ]);
    }
}
