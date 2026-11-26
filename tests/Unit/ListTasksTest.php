<?php

namespace Tests\Unit;

use App\Mcp\Tools\ListTasks;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Mcp\Request;
use Tests\TestCase;

class ListTasksTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_tasks_returns_correct_tasks_for_user()
    {
        Carbon::setTestNow(Carbon::parse('2026-05-20'));

        $user = User::factory()->create();

        // Create tasks for the user
        $task1 = Task::create([
            'title' => 'Task 1',
            'assignee_id' => $user->id,
            'due_date' => '2026-05-19 12:00:00',
            'estimate' => 60,
        ]);

        $task2 = Task::create([
            'title' => 'Task 2',
            'assignee_id' => $user->id,
            'due_date' => '2026-05-20 15:00:00',
            'estimate' => 30,
        ]);

        // Overdue/future task beyond till_date
        $task3 = Task::create([
            'title' => 'Task 3',
            'assignee_id' => $user->id,
            'due_date' => '2026-05-21 09:00:00',
            'estimate' => 45,
        ]);

        // Completed task
        $task4 = Task::create([
            'title' => 'Task 4',
            'assignee_id' => $user->id,
            'due_date' => '2026-05-20 10:00:00',
            'completed_at' => '2026-05-20 10:30:00',
            'estimate' => 60,
        ]);

        // Task for a different user
        $otherUser = User::factory()->create();
        $task5 = Task::create([
            'title' => 'Task 5',
            'assignee_id' => $otherUser->id,
            'due_date' => '2026-05-20 10:00:00',
            'estimate' => 60,
        ]);

        // The first user is the admin, so listing another user's tasks is allowed.
        $this->actingAs($user);

        $tool = new ListTasks;

        // Test listing up to today (2026-05-20), limit = -1
        $result = $tool->handle(new Request([
            'user_id' => $user->id,
            'limit' => -1,
            'till_date' => '2026-05-20',
        ]));

        $response = json_decode((string) $result->content(), true);

        $this->assertEquals('success', $response['status']);
        $this->assertCount(2, $response['tasks']);
        $this->assertEquals('Task 1', $response['tasks'][0]['title']);
        $this->assertEquals('Task 2', $response['tasks'][1]['title']);

        // Test limit
        $resultWithLimit = $tool->handle(new Request([
            'user_id' => $user->id,
            'limit' => 1,
            'till_date' => '2026-05-20',
        ]));

        $responseWithLimit = json_decode((string) $resultWithLimit->content(), true);
        $this->assertCount(1, $responseWithLimit['tasks']);
        $this->assertEquals('Task 1', $responseWithLimit['tasks'][0]['title']);
    }

    public function test_list_tasks_returns_paginated_response()
    {
        Carbon::setTestNow(Carbon::parse('2026-05-20'));

        $user = User::factory()->create();

        foreach (range(1, 30) as $i) {
            Task::create([
                'title' => "Task {$i}",
                'assignee_id' => $user->id,
                'due_date' => Carbon::parse('2026-05-01 09:00:00')->addMinutes($i),
                'estimate' => 30,
            ]);
        }

        $this->actingAs($user);

        $tool = new ListTasks;

        $page1 = json_decode((string) $tool->handle(new Request([
            'user_id' => $user->id,
            'page' => 1,
            'per_page' => 25,
            'till_date' => '2026-05-20',
        ]))->content(), true);

        $this->assertEquals('success', $page1['status']);
        $this->assertCount(25, $page1['tasks']);
        $this->assertEquals('Task 1', $page1['tasks'][0]['title']);
        $this->assertEquals(1, $page1['pagination']['current_page']);
        $this->assertEquals(25, $page1['pagination']['per_page']);
        $this->assertEquals(30, $page1['pagination']['total']);
        $this->assertEquals(2, $page1['pagination']['last_page']);
        $this->assertTrue($page1['pagination']['has_more']);

        $page2 = json_decode((string) $tool->handle(new Request([
            'user_id' => $user->id,
            'page' => 2,
            'per_page' => 25,
            'till_date' => '2026-05-20',
        ]))->content(), true);

        $this->assertCount(5, $page2['tasks']);
        $this->assertEquals('Task 26', $page2['tasks'][0]['title']);
        $this->assertEquals(2, $page2['pagination']['current_page']);
        $this->assertFalse($page2['pagination']['has_more']);
    }

    public function test_list_tasks_accepts_limit_as_per_page_and_caps_page_size()
    {
        Carbon::setTestNow(Carbon::parse('2026-05-20'));

        $user = User::factory()->create();

        foreach (range(1, 3) as $i) {
            Task::create([
                'title' => "Task {$i}",
                'assignee_id' => $user->id,
                'due_date' => Carbon::parse('2026-05-01 09:00:00')->addMinutes($i),
                'estimate' => 30,
            ]);
        }

        $this->actingAs($user);

        $tool = new ListTasks;

        $limited = json_decode((string) $tool->handle(new Request([
            'user_id' => $user->id,
            'limit' => 1,
            'till_date' => '2026-05-20',
        ]))->content(), true);

        $this->assertCount(1, $limited['tasks']);
        $this->assertEquals(1, $limited['pagination']['per_page']);
        $this->assertEquals(3, $limited['pagination']['total']);
        $this->assertEquals(3, $limited['pagination']['last_page']);

        $capped = json_decode((string) $tool->handle(new Request([
            'user_id' => $user->id,
            'per_page' => 500,
            'till_date' => '2026-05-20',
        ]))->content(), true);

        $this->assertEquals(100, $capped['pagination']['per_page']);
        $this->assertCount(3, $capped['tasks']);
        $this->assertFalse($capped['pagination']['has_more']);
    }

    public function test_list_tasks_filters_by_status_and_completed_date()
    {
        Carbon::setTestNow(Carbon::parse('2026-05-20'));

        $user = User::factory()->create();

        $open = Task::create([
            'title' => 'Open task',
            'assignee_id' => $user->id,
            'due_date' => '2026-05-19 12:00:00',
        ]);

        $completedInRange = Task::create([
            'title' => 'Completed in May',
            'assignee_id' => $user->id,
            'due_date' => '2026-05-05 12:00:00',
            'completed_at' => '2026-05-10 12:00:00',
        ]);
        $completedInRange->attachTag('Alpha');

        Task::create([
            'title' => 'Completed in April',
            'assignee_id' => $user->id,
            'due_date' => '2026-04-10 12:00:00',
            'completed_at' => '2026-04-15 12:00:00',
        ]);

        $this->actingAs($user);

        $tool = new ListTasks;

        $default = $this->callTool($tool, ['user_id' => $user->id]);
        $this->assertSame(['Open task'], array_column($default['tasks'], 'title'));
        $this->assertSame('open', $default['tasks'][0]['status']);
        $this->assertNull($default['tasks'][0]['completed_at']);

        $completed = $this->callTool($tool, ['user_id' => $user->id, 'status' => 'completed']);
        $this->assertSame(['Completed in May', 'Completed in April'], array_column($completed['tasks'], 'title'));
        $this->assertSame('completed', $completed['tasks'][0]['status']);
        $this->assertNotNull($completed['tasks'][0]['completed_at']);

        $completedInMay = $this->callTool($tool, [
            'user_id' => $user->id,
            'status' => 'completed',
            'from_date' => '2026-05-01',
            'to_date' => '2026-05-31',
        ]);
        $this->assertSame(['Completed in May'], array_column($completedInMay['tasks'], 'title'));

        $all = $this->callTool($tool, [
            'user_id' => $user->id,
            'status' => 'all',
            'from_date' => '2026-05-01',
            'to_date' => '2026-05-31',
        ]);
        $this->assertSame(['Completed in May', 'Open task'], array_column($all['tasks'], 'title'));

        $completedWithTag = $this->callTool($tool, [
            'user_id' => $user->id,
            'status' => 'completed',
            'tags' => 'Alpha',
        ]);
        $this->assertSame(['Completed in May'], array_column($completedWithTag['tasks'], 'title'));
    }

    public function test_list_tasks_rejects_invalid_status_and_date_range()
    {
        Carbon::setTestNow(Carbon::parse('2026-05-20'));

        $user = User::factory()->create();
        $this->actingAs($user);

        $tool = new ListTasks;

        $invalidStatus = $this->callTool($tool, ['user_id' => $user->id, 'status' => 'archived']);
        $this->assertSame('error', $invalidStatus['status']);

        $invalidRange = $this->callTool($tool, [
            'user_id' => $user->id,
            'status' => 'completed',
            'from_date' => '2026-05-31',
            'to_date' => '2026-05-01',
        ]);
        $this->assertSame('error', $invalidRange['status']);
    }

    private function callTool(ListTasks $tool, array $arguments): array
    {
        return json_decode((string) $tool->handle(new Request($arguments))->content(), true);
    }
}
