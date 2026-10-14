<?php

namespace App\Mcp\Tools;

use App\Models\User;
use Generator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Auth;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ListTeamMembers extends Tool
{
    protected string $title = 'List Team Members';

    /**
     * A description of the tool.
     */
    public function description(): string
    {
        return 'Get a list of all team members and their IDs to use as assignee_id.';
    }

    /**
     * The input schema of the tool.
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    /**
     * Execute the tool call.
     */
    public function handle(Request $request): Response|Generator
    {
        $users = User::query()
            ->when(! Auth::user()?->is_admin, fn ($q) => $q->where(function ($q) {
                $q->where('is_disabled', false)->orWhereNull('is_disabled');
            }))
            ->get(['id', 'name', 'email']);

        return Response::json($users->toArray());
    }
}
