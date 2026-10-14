<?php

namespace App\Mcp\Tools;

use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Generator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Notifications\DatabaseNotification;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ListNotifications extends Tool
{
    protected string $title = 'List Notifications';

    public function description(): string
    {
        return 'List notifications for active users, including their related user and task data. Defaults to unread notifications only.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'user_id' => $schema->integer()->description('The ID of the user whose notifications to list.'),
            'from_date' => $schema->string()->description('The first notification date to include (Y-m-d).'),
            'to_date' => $schema->string()->description('The last notification date to include (Y-m-d).'),
            'includes_read' => $schema->boolean()->description('Set true to include read notifications. Defaults to false.'),
        ];
    }

    public function handle(Request $request): Response|Generator
    {
        try {
            $fromDate = $request->get('from_date') !== null ? Carbon::parse($request->get('from_date'))->startOfDay() : null;
            $toDate = $request->get('to_date') !== null ? Carbon::parse($request->get('to_date'))->endOfDay() : null;
        } catch (\Exception) {
            return Response::error('Invalid date format. Please use Y-m-d format.');
        }

        $notifications = DatabaseNotification::query()
            ->with('notifiable')
            ->where('notifiable_type', User::class)
            ->whereIn('notifiable_id', User::query()
                ->select('id')
                ->where(fn ($query) => $query->where('is_disabled', false)->orWhereNull('is_disabled')))
            ->when($request->get('user_id') !== null, fn ($query) => $query->where('notifiable_id', $request->get('user_id')))
            ->when(! ($request->get('includes_read') ?? false), fn ($query) => $query->whereNull('read_at'))
            ->when($fromDate, fn ($query) => $query->where('created_at', '>=', $fromDate))
            ->when($toDate, fn ($query) => $query->where('created_at', '<=', $toDate))
            ->latest()
            ->get();

        $tasks = Task::whereKey($notifications->pluck('data.task_id')->filter()->unique())
            ->get()
            ->keyBy('id');

        return Response::json([
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
