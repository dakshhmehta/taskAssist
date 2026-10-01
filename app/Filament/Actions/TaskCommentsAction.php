<?php

namespace App\Filament\Actions;

use App\Models\Task;
use Parallax\FilamentComments\Tables\Actions\CommentsAction;

class TaskCommentsAction
{
    /**
     * The Filament comments table action, labelled with the task's comment count
     * so it reads like "Comment (3)". Listings should eager-load the count via
     * withCount('filamentComments'); otherwise the label falls back to a direct
     * count query.
     */
    public static function make(): CommentsAction
    {
        return CommentsAction::make()
            ->label(fn (Task $record): string => self::label($record));
    }

    /**
     * The shared "Comment (n)" label format.
     */
    public static function label(Task $task): string
    {
        $count = $task->filament_comments_count ?? $task->filamentComments()->count();

        return "Comment ({$count})";
    }
}
