<?php

namespace App\Mcp\Tools;

use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Generator;
use Illuminate\Notifications\DatabaseNotification;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\Title;
use Laravel\Mcp\Server\Tools\ToolInputSchema;
use Laravel\Mcp\Server\Tools\ToolResult;

#[Title('List Notifications')]
class ListNotifications extends Tool
{
    public function description(): string
    {
        return 'List notifications for active users, including their related user and task data. Defaults to unread notifications only.';
    }

    public function schema(ToolInputSchema $schema): ToolInputSchema
    {
        return $schema->integer('user_id', 'The ID of the user whose notifications to list.')
            ->string('from_date', 'The first notification date to include (Y-m-d).')
            ->string('to_date', 'The last notification date to include (Y-m-d).')
            ->boolean('includes_read', 'Set true to include read notifications. Defaults to false.');
    }

    public function handle(array $arguments): ToolResult|Generator
    {
        try {
            $fromDate = isset($arguments['from_date']) ? Carbon::parse($arguments['from_date'])->startOfDay() : null;
            $toDate = isset($arguments['to_date']) ? Carbon::parse($arguments['to_date'])->endOfDay() : null;
        } catch (\Exception) {
            return ToolResult::error('Invalid date format. Please use Y-m-d format.');
        }

        $notifications = DatabaseNotification::query()
            ->with('notifiable')
            ->where('notifiable_type', User::class)
            ->whereIn('notifiable_id', User::query()
                ->select('id')
                ->where(fn ($query) => $query->where('is_disabled', false)->orWhereNull('is_disabled')))
            ->when(isset($arguments['user_id']), fn ($query) => $query->where('notifiable_id', $arguments['user_id']))
            ->when(! ($arguments['includes_read'] ?? false), fn ($query) => $query->whereNull('read_at'))
            ->when($fromDate, fn ($query) => $query->where('created_at', '>=', $fromDate))
            ->when($toDate, fn ($query) => $query->where('created_at', '<=', $toDate))
            ->latest()
            ->get();

        $tasks = Task::whereKey($notifications->pluck('data.task_id')->filter()->unique())
            ->get()
            ->keyBy('id');

        return ToolResult::json([
            'status' => 'success',
            'notifications' => $notifications->map(fn (DatabaseNotification $notification) => [
                'id' => $notification->id,
                'type' => $notification->type,
                'data' => $notification->data,
                'read_at' => $notification->read_at,
                'created_at' => $notification->created_at,
                'user' => $notification->notifiable?->only(['id', 'name', 'email']),
                'task' => $tasks->get($notification->data['task_id'] ?? null)?->toArray(),
            ])->values()->all(),
        ]);
    }
}
