<?php

namespace Tests\Unit;

use App\Models\Task;
use App\Models\Timesheet;
use App\Models\User;
use App\Services\ProjectTrackerReport;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTrackerReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_hours_are_included_by_default()
    {
        [$owner, $alice, $bob, $romin] = $this->seedMonth();

        $report = (new ProjectTrackerReport)->build(Carbon::parse('2026-10-01'));

        // Team (alice 180 + bob 180) plus owners (45 + 30).
        $this->assertSame(435, $report['totals']['minutes']);
        $this->assertSame(7.3, $report['totals']['hours']);
        $this->assertSame(3, $report['totals']['projects']);
        $this->assertSame(3, $report['totals']['tasks']);
        $this->assertSame(4, $report['totals']['members']);

        $this->assertSame(
            $report['totals']['minutes'],
            collect($report['projects'])->sum('minutes')
        );
        $this->assertSame(
            $report['totals']['minutes'],
            collect($report['members'])->sum('minutes')
        );

        $byName = collect($report['projects'])->keyBy('name');
        $this->assertSame(180, $byName['TheBullTrade']['minutes']);
        $this->assertSame(195, $byName['Sanats-Gym-App']['minutes']);
        $this->assertSame(60, $byName['(no tag)']['minutes']);
        $this->assertSame(3, $byName['Sanats-Gym-App']['people']);

        $members = collect($report['members'])->keyBy('name');
        $this->assertSame(45, $members[$owner->name]['minutes']);
        $this->assertSame(30, $members[$romin->name]['minutes']);

        $this->assertTrue($report['excluded']['included']);
        $this->assertSame(75, $report['excluded']['minutes']);
        $this->assertSame(2, $report['excluded']['entries']);
    }

    public function test_it_builds_project_and_member_cuts_when_owner_excluded()
    {
        [$owner, $alice, $bob] = $this->seedMonth();

        $report = (new ProjectTrackerReport)->build(Carbon::parse('2026-10-01'), includeOwner: false);

        $this->assertSame(360, $report['totals']['minutes']);
        $this->assertSame(6.0, $report['totals']['hours']);
        $this->assertSame(3, $report['totals']['projects']);
        $this->assertSame(3, $report['totals']['tasks']);
        $this->assertSame(2, $report['totals']['members']);

        $project = $report['projects'][0];
        $this->assertSame('TheBullTrade', $project['name']);
        $this->assertSame(180, $project['minutes']);
        $this->assertSame(3.0, $project['hours']);
        $this->assertSame(50.0, $project['share']);
        $this->assertSame(1, $project['tasks']);
        $this->assertSame(2, $project['people']);
        $this->assertSame([
            ['name' => $alice->name, 'minutes' => 120, 'hours' => 2.0, 'share' => 66.7],
            ['name' => $bob->name, 'minutes' => 60, 'hours' => 1.0, 'share' => 33.3],
        ], $project['members']);

        $byName = collect($report['projects'])->keyBy('name');
        $this->assertSame(120, $byName['Sanats-Gym-App']['minutes']);
        $this->assertSame(60, $byName['(no tag)']['minutes']);

        $this->assertSame(
            $report['totals']['minutes'],
            collect($report['projects'])->sum('minutes')
        );
        $this->assertSame(
            $report['totals']['minutes'],
            collect($report['members'])->sum('minutes')
        );

        $members = collect($report['members'])->keyBy('name');
        $this->assertSame(180, $members[$alice->name]['minutes']);
        $this->assertSame(50.0, $members[$alice->name]['share']);
        $this->assertSame(2, $members[$alice->name]['projects']);

        $this->assertFalse($report['excluded']['included']);
        $this->assertSame(75, $report['excluded']['minutes']);
        $this->assertSame(1.3, $report['excluded']['hours']);
        $this->assertSame(2, $report['excluded']['entries']);
    }

    /**
     * @return array{0: User, 1: User, 2: User, 3: User}
     */
    private function seedMonth(): array
    {
        $owner = User::factory()->create();          // id 1 — owner
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $romin = User::factory()->create(['id' => 8]); // owner

        $bullTrade = Task::create(['title' => 'Bull trade work', 'assignee_id' => $alice->id]);
        $bullTrade->attachTag('TheBullTrade');
        $bullTrade->attachTag('SecondTag');

        $gym = Task::create(['title' => 'Gym app', 'assignee_id' => $bob->id]);
        $gym->attachTag('Sanats-Gym-App');

        $untagged = Task::create(['title' => 'Untagged work', 'assignee_id' => $alice->id]);

        $nextMonth = Task::create(['title' => 'November work', 'assignee_id' => $alice->id]);

        $this->log($alice, $bullTrade, '2026-10-02 10:00:00', 90);
        $this->log($alice, $bullTrade, '2026-10-10 10:00:00', 30);
        $this->log($bob, $bullTrade, '2026-10-05 10:00:00', 60);
        $this->log($bob, $gym, '2026-10-06 10:00:00', 120);
        $this->log($alice, $untagged, '2026-10-07 10:00:00', 60);
        $this->log($owner, $gym, '2026-10-08 10:00:00', 45);
        $this->log($romin, $gym, '2026-10-09 10:00:00', 30);
        $this->log($alice, $nextMonth, '2026-11-01 10:00:00', 500);

        // A timer still running must not count.
        Timesheet::create([
            'user_id' => $alice->id,
            'task_id' => $bullTrade->id,
            'start_at' => '2026-10-09 10:00:00',
            'end_at' => null,
        ]);

        return [$owner, $alice, $bob, $romin];
    }

    private function log(User $user, Task $task, string $start, int $minutes): void
    {
        $start = Carbon::parse($start);

        Timesheet::create([
            'user_id' => $user->id,
            'task_id' => $task->id,
            'start_at' => $start,
            'end_at' => $start->copy()->addMinutes($minutes),
        ]);
    }
}
