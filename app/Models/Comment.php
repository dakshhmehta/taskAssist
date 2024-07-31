<?php

namespace App\Models;

use App\Actions\ReplyOnCommentAction;
use Filament\Notifications\Notification;
use Parallax\FilamentComments\Models\FilamentComment;

class Comment extends FilamentComment
{
    protected static function booted(): void
    {
        static::created(function (Comment $comment) {
            if ($comment->user_id == $comment->subject->assignee_id) {
                // Notify admins
                $users = User::all();

                foreach ($users as $user) {
                    if ($user->is_admin) {
                        Notification::make()
                            ->title($comment->user->name . ' commented on ' . $comment->subject->title)
                            ->body($comment->comment)
                            ->sendToDatabase($user);
                    }
                }
            } else {
                Notification::make()
                    ->title($comment->user->name . ' commented on ' . $comment->subject->title)
                    ->body($comment->comment)
                    ->sendToDatabase($comment->subject->assignee);
            }
        });
    }
}
