<?php

namespace Tests\Unit;

use App\Mcp\Tools\SetTimer;
use App\Models\Task;
use App\Models\Timesheet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Mcp\Request;
use Tests\TestCase;

class SetTimerTest extends TestCase
{
    use RefreshDatabase;

    private function handle(array $arguments): array
    {
        $result = (new SetTimer)->handle(new Request($arguments));

        return json_decode((string) $result->content(), true);
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

    public function test_team_member_cannot_start_timer_on_another_users_task(): void
    {
        User::factory()->create(); // admin
        $member = User::factory()->create();
        $other = User::factory()->create();
        $otherTask = $this->createTask($other, 'Their task');

        $this->actingAs($member);

        $payload = $this->handle(['task_id' => $otherTask->id, 'action' => 'start']);

        $this->assertSame('error', $payload['status']);
        $this->assertStringContainsString('not assigned to you', $payload['message']);
        $this->assertSame(0, $this->runningTimesheets($member->id));
        $this->assertSame(0, $this->runningTimesheets($other->id));
    }

    public function test_team_member_can_start_and_stop_timer_on_own_task(): void
    {
        User::factory()->create(); // admin
        $member = User::factory()->create();
        $task = $this->createTask($member);

        $this->actingAs($member);

        $started = $this->handle(['task_id' => $task->id, 'action' => 'start']);
        $stopped = $this->handle(['task_id' => $task->id, 'action' => 'stop']);

        $this->assertSame('success', $started['status']);
        $this->assertSame('success', $stopped['status']);
        $this->assertSame(0, $this->runningTimesheets($member->id));
    }

    public function test_team_member_cannot_stop_timer_on_another_users_task(): void
    {
        User::factory()->create(); // admin
        $member = User::factory()->create();
        $other = User::factory()->create();
        $otherTask = $this->createTask($other, 'Their task');

        $timesheet = Timesheet::create([
            'user_id' => $member->id,
            'task_id' => $otherTask->id,
            'start_at' => now(),
        ]);

        $this->actingAs($member);

        $payload = $this->handle(['task_id' => $otherTask->id, 'action' => 'stop']);

        $this->assertSame('error', $payload['status']);
        $this->assertStringContainsString('not assigned to you', $payload['message']);
        $this->assertNull($timesheet->fresh()->end_at);
    }

    public function test_admin_cannot_start_or_stop_timer_on_another_users_task(): void
    {
        $admin = User::factory()->create();
        $other = User::factory()->create();
        $otherTask = $this->createTask($other, 'Their task');

        $this->actingAs($admin);

        $start = $this->handle(['task_id' => $otherTask->id, 'action' => 'start']);

        $this->assertSame('error', $start['status']);
        $this->assertSame(0, $this->runningTimesheets($admin->id));
        $this->assertSame(0, $this->runningTimesheets($other->id));

        $timesheet = Timesheet::create([
            'user_id' => $admin->id,
            'task_id' => $otherTask->id,
            'start_at' => now(),
        ]);

        $stop = $this->handle(['task_id' => $otherTask->id, 'action' => 'stop']);

        $this->assertSame('error', $stop['status']);
        $this->assertNull($timesheet->fresh()->end_at);
    }

    public function test_stop_without_task_id_is_not_restricted_by_assignment(): void
    {
        User::factory()->create(); // admin
        $member = User::factory()->create();
        $task = $this->createTask($member);

        $this->actingAs($member);

        $this->handle(['task_id' => $task->id, 'action' => 'start']);

        $payload = $this->handle(['action' => 'stop']);

        $this->assertSame('success', $payload['status']);
        $this->assertSame(1, $payload['stopped']);
        $this->assertSame(0, $this->runningTimesheets($member->id));
    }
}
