<?php

namespace Tests\Unit;

use App\Mcp\Tools\CompleteTask;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class CompleteTaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_task_adds_closing_remarks_as_comment_when_provided(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $task = Task::create([
            'title' => 'Wrap up implementation',
            'assignee_id' => $user->id,
        ]);

        $this->actingAs($user);

        $tool = new CompleteTask();
        $result = $tool->handle([
            'timepro_task_id' => $task->id,
            'closing_remarks' => 'Implemented the fix and verified the result.',
        ]);

        $payload = json_decode($result->toArray()['content'][0]['text'], true);

        $this->assertSame('success', $payload['status']);
        $this->assertNotNull($task->fresh()->completed_at);
        $this->assertSame(
            'Implemented the fix and verified the result.',
            $task->fresh()->filamentComments()->latest()->first()?->comment
        );
    }

    public function test_complete_task_keeps_closing_remarks_optional(): void
    {
        Bus::fake();

        $user = User::factory()->create();
        $task = Task::create([
            'title' => 'Close without note',
            'assignee_id' => $user->id,
        ]);

        $this->actingAs($user);

        $tool = new CompleteTask();
        $result = $tool->handle([
            'timepro_task_id' => $task->id,
        ]);

        $payload = json_decode($result->toArray()['content'][0]['text'], true);

        $this->assertSame('success', $payload['status']);
        $this->assertNotNull($task->fresh()->completed_at);
        $this->assertSame(0, $task->fresh()->filamentComments()->count());
    }
}
