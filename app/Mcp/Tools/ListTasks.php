<?php

namespace App\Mcp\Tools;

use App\Models\Task;
use Carbon\Carbon;
use Generator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ListTasks extends Tool
{
    protected string $title = 'List Tasks';

    /**
     * A description of the tool.
     */
    public function description(): string
    {
        return 'List tasks for a specific user, filtered by a maximum due date, optional limit, and optional tags.';
    }

    /**
     * The input schema of the tool.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'user_id' => $schema->integer()->description('The ID of the user to list tasks for. Defaults to the logged-in user.'),
            'limit' => $schema->integer()->description('The limit number of tasks to return (defaults to -1 for no limit)'),
            'till_date' => $schema->string()->description('The date (Y-m-d format) to list tasks up to (defaults to today)'),
            'tags' => $schema->string()->description('Comma-separated tag names to filter tasks (e.g. "bug,urgent"). Tasks matching any tag are returned.'),
        ];
    }

    /**
     * Execute the tool call.
     */
    public function handle(Request $request): Response|Generator
    {
        $userId = $request->get('user_id') ?? Auth::id();
        $limit = $request->get('limit') ?? -1;
        $tags = $request->get('tags') ?? null;

        try {
            $tillDate = $request->get('till_date') !== null
                ? Carbon::parse($request->get('till_date'))->endOfDay()
                : now()->endOfDay();
        } catch (\Exception $e) {
            return Response::error('Invalid till_date format. Please use Y-m-d format.');
        }

        $query = Task::where('assignee_id', $userId)
            ->whereNull('completed_at')
            ->where('due_date', '<=', $tillDate)
            ->orderBy('due_date', 'ASC');

        if ($tags) {
            $tagNames = array_map('trim', explode(',', $tags));
            $query->withAnyTags($tagNames);
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $tasks = $query->get();

        return Response::json([
            'status' => 'success',
            'tasks' => $tasks->map(fn ($task) => [
                'id' => $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'due_date' => $task->due_date,
                'estimate' => $task->estimate,
                'is_urgent' => $task->is_urgent,
                'is_important' => $task->is_important,
                'project' => $task->tag,
            ])->toArray(),
        ]);
    }
}
