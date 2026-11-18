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
        return 'List tasks for a specific user, paginated, filtered by a maximum due date, page size, and optional tags. Returns tasks plus pagination metadata (total, last_page, has_more).';
    }

    /**
     * The input schema of the tool.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'user_id' => $schema->integer()->description('The ID of the user to list tasks for. Defaults to the logged-in user.'),
            'page' => $schema->integer()->description('The page of results to return (defaults to 1)'),
            'per_page' => $schema->integer()->description('The number of tasks per page (defaults to 25, max 100)'),
            'limit' => $schema->integer()->description('Alias for per_page, kept for backwards compatibility. -1 uses the default page size.'),
            'till_date' => $schema->string()->description('The date (Y-m-d format) to list tasks up to (defaults to today)'),
            'tags' => $schema->string()->description('Comma-separated tag names to filter tasks (e.g. "bug,urgent"). Tasks matching any tag are returned.'),
        ];
    }

    /**
     * Execute the tool call.
     */
    public function handle(Request $request): Response|Generator
    {
        $user = Auth::user();

        if (! $user) {
            return Response::error('No authenticated user.');
        }

        $userId = $request->get('user_id') ?? $user->id;

        // Team members can only list their own tasks.
        if (! $user->is_admin && (int) $userId !== (int) $user->id) {
            return Response::json([
                'status' => 'error',
                'message' => 'Team members can only list their own tasks.',
            ]);
        }

        // limit:-1 used to mean "no limit"; a paginated response falls back to the default page size.
        $perPage = (int) ($request->get('per_page') ?? $request->get('limit') ?? 25);
        $perPage = $perPage > 0 ? min($perPage, 100) : 25;
        $page = max((int) ($request->get('page') ?? 1), 1);
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

        $tasks = $query->paginate($perPage, ['*'], 'page', $page);

        return Response::json([
            'status' => 'success',
            'tasks' => $tasks->getCollection()->map(fn ($task) => [
                'id' => $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'due_date' => $task->due_date,
                'estimate' => $task->estimate,
                'is_urgent' => $task->is_urgent,
                'is_important' => $task->is_important,
                'project' => $task->tag,
            ])->toArray(),
            'pagination' => [
                'current_page' => $tasks->currentPage(),
                'per_page' => $tasks->perPage(),
                'total' => $tasks->total(),
                'last_page' => $tasks->lastPage(),
                'has_more' => $tasks->hasMorePages(),
            ],
        ]);
    }
}
