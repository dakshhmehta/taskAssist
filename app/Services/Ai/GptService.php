<?php

namespace App\Services\Ai;

use OpenAI\Client;
use App\Services\Ai\ToolExecutor;

class GptService
{
    protected Client $client;

    public function __construct()
    {
        $this->client = \OpenAI::client(env('OPENAI_API_KEY'));
    }

    public function handle(string $userMessage): string
    {
        $tools = config('ai_tools');

        // 🔹 Step 1: Ask GPT what to do
        $response = $this->client->chat()->create([
            'model' => 'gpt-4.1',
            'messages' => [
                ['role' => 'system', 'content' => 'You are a helpful assistant.'],
                ['role' => 'user', 'content' => $userMessage],
            ],
            'tools' => array_map(fn($tool) => [
                'type' => 'function',
                'function' => $tool
            ], $tools),
        ]);

        $message = $response->choices[0]->message;

        // 🔹 Step 2: If no tool call → return direct response
        if (empty($message->toolCalls)) {
            return $message->content ?? '';
        }

        // 🔹 Step 3: Execute ALL tool calls (important for future scaling)
        $toolMessages = [];
        $assistantToolCalls = [];

        foreach ($message->toolCalls as $toolCall) {
            $toolName = $toolCall->function->name;

            $arguments = json_decode(
                $toolCall->function->arguments ?? '{}',
                true
            );

            try {
                $result = ToolExecutor::execute($toolName, $arguments);
            } catch (\Throwable $e) {
                $result = [
                    'error' => true,
                    'message' => $e->getMessage(),
                ];
            }

            // Collect tool responses
            $toolMessages[] = [
                'role' => 'tool',
                'tool_call_id' => $toolCall->id,
                'content' => json_encode($result),
            ];

            // Rebuild assistant tool_calls (IMPORTANT: no content field)
            $assistantToolCalls[] = [
                'id' => $toolCall->id,
                'type' => 'function',
                'function' => [
                    'name' => $toolName,
                    'arguments' => json_encode($arguments),
                ],
            ];
        }

        // 🔹 Step 4: Send tool results back to GPT
        $finalResponse = $this->client->chat()->create([
            'model' => 'gpt-4.1',
            'messages' => array_merge([
                ['role' => 'system', 'content' => 'You are a helpful assistant.'],
                ['role' => 'user', 'content' => $userMessage],
                [
                    'role' => 'assistant',
                    'tool_calls' => $assistantToolCalls,
                ],
            ], $toolMessages),
        ]);

        return $finalResponse->choices[0]->message->content ?? 'No response.';
    }
}