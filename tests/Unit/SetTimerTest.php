<?php

namespace Tests\Unit;

use App\Mcp\Tools\SetTimer;
use App\Models\Task;
use App\Models\Timesheet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetTimerTest extends TestCase
{
    use RefreshDatabase;

    private function handle(array $arguments): array
    {
        $result = (new SetTimer)->handle($arguments);

        return json_decode($result->toArray()['content'][0]['text'], true);
    }

    private function runningTimesheets(int $userId): int
    {
        return Timesheet::where('user_id', $userId)->whereNull('end_at')->count();
    }

    private function createTask(User $user, string $title = 'Some task'): Task
    {
        return Task::create([
            'title' => $title,
            'assignee_id' => $user->id,
        ]);
    }

    public function test_start_creates_running_timesheet_for_task(): void
    {
        $user = User::factory()->create();
        $task = $this->createTask($user);

        $this->actingAs($user);

        $payload = $this->handle([
            'task_id' => $task->id,
            'action' => 'start',
        ]);

        $this->assertSame('success', $payload['status']);
        $this->assertSame(1, $this->runningTimesheets($user->id));
        $this->assertTrue($task->timesheet()->working()->exists());
    }

    public function test_start_on_second_task_keeps_first_timer_running(): void
    {
        $user = User::factory()->create();
        $taskA = $this->createTask($user, 'Task A');
        $taskB = $this->createTask($user, 'Task B');

        $this->actingAs($user);

        $first = $this->handle([
            'task_id' => $taskA->id,
            'action' => 'start',
        ]);

        $second = $this->handle([
            'task_id' => $taskB->id,
            'action' => 'start',
        ]);

        $this->assertSame('success', $first['status']);
        $this->assertSame('success', $second['status']);
        $this->assertSame(2, $this->runningTimesheets($user->id));
        $this->assertTrue($taskA->timesheet()->working()->exists());
        $this->assertTrue($taskB->timesheet()->working()->exists());
    }

    public function test_start_same_task_twice_returns_error(): void
    {
        $user = User::factory()->create();
        $task = $this->createTask($user);

        $this->actingAs($user);

        $this->handle(['task_id' => $task->id, 'action' => 'start']);

        $payload = $this->handle(['task_id' => $task->id, 'action' => 'start']);

        $this->assertSame('error', $payload['status']);
        $this->assertSame(1, $this->runningTimesheets($user->id));
    }

    public function test_start_requires_task_id(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $payload = $this->handle(['action' => 'start']);

        $this->assertSame('error', $payload['status']);
        $this->assertSame(0, $this->runningTimesheets($user->id));
    }

    public function test_stop_with_task_id_stops_only_that_timer(): void
    {
        $user = User::factory()->create();
        $taskA = $this->createTask($user, 'Task A');
        $taskB = $this->createTask($user, 'Task B');

        $this->actingAs($user);

        $this->handle(['task_id' => $taskA->id, 'action' => 'start']);
        $this->handle(['task_id' => $taskB->id, 'action' => 'start']);

        $payload = $this->handle(['task_id' => $taskA->id, 'action' => 'stop']);

        $this->assertSame('success', $payload['status']);
        $this->assertSame(1, $this->runningTimesheets($user->id));
        $this->assertFalse($taskA->timesheet()->working()->exists());
        $this->assertTrue($taskB->timesheet()->working()->exists());
    }

    public function test_stop_without_task_id_stops_all_running_timers(): void
    {
        $user = User::factory()->create();
        $taskA = $this->createTask($user, 'Task A');
        $taskB = $this->createTask($user, 'Task B');

        $this->actingAs($user);

        $this->handle(['task_id' => $taskA->id, 'action' => 'start']);
        $this->handle(['task_id' => $taskB->id, 'action' => 'start']);

        $payload = $this->handle(['action' => 'stop']);

        $this->assertSame('success', $payload['status']);
        $this->assertSame(2, $payload['stopped']);
        $this->assertSame(0, $this->runningTimesheets($user->id));
    }

    public function test_stop_without_task_id_when_none_running_returns_message(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $payload = $this->handle(['action' => 'stop']);

        $this->assertSame('success', $payload['status']);
        $this->assertSame(0, $payload['stopped']);
        $this->assertSame(0, $this->runningTimesheets($user->id));
    }

    public function test_stop_without_task_id_only_affects_authenticated_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $task = $this->createTask($user);
        $otherTask = $this->createTask($otherUser, 'Other user task');

        $this->actingAs($user);

        $this->handle(['task_id' => $task->id, 'action' => 'start']);

        $otherUserTimesheet = Timesheet::create([
            'user_id' => $otherUser->id,
            'task_id' => $otherTask->id,
            'start_at' => now(),
        ]);

        $payload = $this->handle(['action' => 'stop']);

        $this->assertSame('success', $payload['status']);
        $this->assertSame(1, $payload['stopped']);
        $this->assertSame(0, $this->runningTimesheets($user->id));
        $this->assertNull($otherUserTimesheet->fresh()->end_at, 'Other user timer must remain running');
    }

    public function test_invalid_action_returns_error(): void
    {
        $user = User::factory()->create();
        $task = $this->createTask($user);

        $this->actingAs($user);

        $payload = $this->handle(['task_id' => $task->id, 'action' => 'pause']);

        $this->assertSame('error', $payload['status']);
        $this->assertSame(0, $this->runningTimesheets($user->id));
    }
}
