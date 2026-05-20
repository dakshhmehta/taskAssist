<?php

namespace Tests\Unit;

use App\Mcp\Tools\ListTasks;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $tool = new ListTasks();

        // Test listing up to today (2026-05-20), limit = -1
        $result = $tool->handle([
            'user_id' => $user->id,
            'limit' => -1,
            'till_date' => '2026-05-20',
        ]);

        $array = $result->toArray();
        $response = json_decode($array['content'][0]['text'], true);

        $this->assertEquals('success', $response['status']);
        $this->assertCount(2, $response['tasks']);
        $this->assertEquals('Task 1', $response['tasks'][0]['title']);
        $this->assertEquals('Task 2', $response['tasks'][1]['title']);

        // Test limit
        $resultWithLimit = $tool->handle([
            'user_id' => $user->id,
            'limit' => 1,
            'till_date' => '2026-05-20',
        ]);

        $arrayWithLimit = $resultWithLimit->toArray();
        $responseWithLimit = json_decode($arrayWithLimit['content'][0]['text'], true);
        $this->assertCount(1, $responseWithLimit['tasks']);
        $this->assertEquals('Task 1', $responseWithLimit['tasks'][0]['title']);
    }
}
