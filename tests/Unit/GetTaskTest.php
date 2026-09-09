<?php

namespace Tests\Unit;

use App\Mcp\Servers\TaskServer;
use App\Mcp\Tools\GetTask;
use App\Models\Task;
use App\Models\Timesheet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetTaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_full_task_details_with_all_relations(): void
    {
        $assignee = User::factory()->create(['name' => 'Task Owner']);
        $task = Task::create([
            'title' => 'Review quarterly report',
            'assignee_id' => $assignee->id,
            'estimate' => 60,
        ]);
        $task->attachTag('taskAssist');

        Timesheet::create([
            'user_id' => $assignee->id,
            'task_id' => $task->id,
            'start_at' => now()->subHour(),
            'end_at' => now(),
        ]);

        $task->filamentComments()->create([
            'subject_type' => $task->getMorphClass(),
            'user_id' => $assignee->id,
            'comment' => 'Started the review.',
        ]);

        activity()->performedOn($task)->log('Task viewed');

        $result = (new GetTask)->handle(['timepro_task_id' => $task->id]);
        $payload = json_decode($result->toArray()['content'][0]['text'], true);

        $this->assertSame('success', $payload['status']);
        $this->assertSame($task->id, $payload['task']['id']);
        $this->assertSame($assignee->id, $payload['task']['assignee']['id']);
        $this->assertSame('Task Owner', $payload['task']['assignee']['name']);
        $this->assertContains('taskAssist', collect($payload['task']['tags'])->pluck('name')->all());
        $this->assertCount(1, $payload['task']['timesheet']);
        $this->assertCount(1, $payload['task']['comments']);
        $this->assertSame('Started the review.', $payload['task']['comments'][0]['comment']);
        $this->assertNotEmpty($payload['task']['activity_log']);
    }

    public function test_it_returns_error_for_an_unknown_task(): void
    {
        $result = (new GetTask)->handle(['timepro_task_id' => 999999]);
        $payload = json_decode($result->toArray()['content'][0]['text'], true);

        $this->assertSame('error', $payload['status']);
    }

    public function test_it_is_registered_with_the_task_server(): void
    {
        $this->assertContains(GetTask::class, (new TaskServer)->tools);
    }
}
