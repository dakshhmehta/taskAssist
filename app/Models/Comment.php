<?php

namespace App\Models;

use App\Notifications\NewCommentPostedOnTaskNotification;
use App\Traits\CustomLogOptions;
use Parallax\FilamentComments\Models\FilamentComment;
use Spatie\Activitylog\Traits\LogsActivity;

class Comment extends FilamentComment
{
    use CustomLogOptions, LogsActivity;

    protected static function booted(): void
    {
        static::created(function (Comment $comment) {
            $task = $comment->subject;

            if ($task instanceof Task) {
                $task->notifyAdminAndAssignee(
                    new NewCommentPostedOnTaskNotification($comment),
                    $comment->user_id
                );
            }
        });
    }
}
