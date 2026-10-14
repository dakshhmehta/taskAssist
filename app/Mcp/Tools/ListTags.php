<?php

namespace App\Mcp\Tools;

use App\Models\Tag;
use Generator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;

class ListTags extends Tool
{
    protected string $title = 'List Tags';

    /**
     * A description of the tool.
     */
    public function description(): string
    {
        return 'List all available tags (projects) in the system.';
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
        $tags = Tag::orderBy('name', 'ASC')->get(['id', 'name']);

        return Response::json([
            'tags' => $tags->map(fn ($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
            ])->toArray(),
        ]);
    }
}
