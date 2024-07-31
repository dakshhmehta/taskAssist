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

                $replyBtn = ReplyOnCommentAction::make('reply-comment')
                    ->record($comment->subject);

                // dd($replyBtn);

                foreach ($users as $user) {
                    if ($user->is_admin) {
                        Notification::make()
                            ->title('A new comment posted by ' . $comment->user->name . ' on a task ' . $comment->subject->title)
                            ->body($comment->comment)
                            // ->actions([
                            //     clone $replyBtn
                            // ])
                            ->sendToDatabase($user);

                        // $user->notify(new NewCommentPostedOnTaskNotification($comment));
                    }
                }
            }
        });
    }
}
