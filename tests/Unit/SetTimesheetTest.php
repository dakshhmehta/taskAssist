<?php

namespace Tests\Unit;

use App\Mcp\Tools\SetTimesheet;
use App\Models\Task;
use App\Models\Timesheet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetTimesheetTest extends TestCase
{
    use RefreshDatabase;

    public function test_set_timesheet_accepts_string_dates_from_json_entries(): void
    {
        $user = User::factory()->create();

        $task = Task::create([
            'title' => 'Fix timesheet entries',
            'assignee_id' => $user->id,
            'completed_at' => '2026-06-05 15:30:00',
        ]);

        $this->actingAs($user);

        $tool = new SetTimesheet();

        $result = $tool->handle([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'entries' => json_encode([
                [
                    'duration_minutes' => 50,
                    'date' => '2026-06-04',
                ],
                [
                    'duration_minutes' => 25,
                    'date' => '2026-06-05',
                ],
            ], JSON_THROW_ON_ERROR),
            'reason' => 'Corrected logged work',
        ]);

        $payload = json_decode($result->toArray()['content'][0]['text'], true);

        $this->assertSame('success', $payload['status']);
        $this->assertSame(2, $payload['created']);

        $entries = Timesheet::where('task_id', $task->id)
            ->where('user_id', $user->id)
            ->orderBy('start_at')
            ->get();

        $this->assertCount(2, $entries);
        $this->assertSame('2026-06-04 00:00:00', $entries[0]->start_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-06-04 00:50:00', $entries[0]->end_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-06-05 00:00:00', $entries[1]->start_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-06-05 00:25:00', $entries[1]->end_at->format('Y-m-d H:i:s'));
    }
}
