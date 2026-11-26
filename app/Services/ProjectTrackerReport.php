<?php

namespace App\Services;

use App\Models\Timesheet;
use Carbon\CarbonInterface;

class ProjectTrackerReport
{
    /**
     * @param  array<int>  $ownerIds
     */
    public function __construct(private readonly array $ownerIds = [1, 8]) {}

    /**
     * Hours per project (task's first tag) and per member for one calendar month.
     * Owner entries can be excluded from both cuts and are always reported separately.
     */
    public function build(CarbonInterface $month, bool $includeOwner = true): array
    {
        $start = $month->copy()->startOfMonth();
        $end = $start->copy()->addMonth();

        $entries = Timesheet::query()
            ->with(['task' => fn ($query) => $query->withoutGlobalScope('excludeIgnored'), 'task.tags', 'user'])
            ->whereNotNull('end_at')
            ->where('start_at', '>=', $start)
            ->where('start_at', '<', $end)
            ->get()
            ->map(fn (Timesheet $entry) => [
                'task_id' => $entry->task_id,
                'user_id' => $entry->user_id,
                'user_name' => $entry->user?->name,
                'project' => $entry->task?->tags?->first()?->name ?? '(no tag)',
                'minutes' => (int) $entry->start_at->diffInMinutes($entry->end_at),
            ]);

        $owners = $entries->whereIn('user_id', $this->ownerIds);
        $team = $includeOwner ? $entries : $entries->whereNotIn('user_id', $this->ownerIds);
        $total = (int) $team->sum('minutes');

        $projects = $team->groupBy('project')
            ->map(function ($rows, $name) use ($total) {
                $minutes = (int) $rows->sum('minutes');

                return [
                    'name' => $name,
                    'minutes' => $minutes,
                    'hours' => round($minutes / 60, 1),
                    'share' => $this->share($minutes, $total),
                    'tasks' => $rows->pluck('task_id')->unique()->count(),
                    'people' => $rows->pluck('user_id')->unique()->count(),
                    'members' => $rows->groupBy('user_id')
                        ->map(fn ($memberRows) => [
                            'name' => $memberRows->first()['user_name'],
                            'minutes' => (int) $memberRows->sum('minutes'),
                            'hours' => round($memberRows->sum('minutes') / 60, 1),
                            'share' => $this->share((int) $memberRows->sum('minutes'), $minutes),
                        ])
                        ->sortByDesc('minutes')
                        ->values()
                        ->all(),
                ];
            })
            ->sortByDesc('minutes')
            ->values()
            ->all();

        $members = $team->groupBy('user_id')
            ->map(function ($rows) use ($total) {
                $minutes = (int) $rows->sum('minutes');

                return [
                    'name' => $rows->first()['user_name'],
                    'minutes' => $minutes,
                    'hours' => round($minutes / 60, 1),
                    'share' => $this->share($minutes, $total),
                    'projects' => $rows->pluck('project')->unique()->count(),
                ];
            })
            ->sortByDesc('minutes')
            ->values()
            ->all();

        return [
            'month' => $start->format('Y-m'),
            'totals' => [
                'minutes' => $total,
                'hours' => round($total / 60, 1),
                'projects' => count($projects),
                'tasks' => $team->pluck('task_id')->unique()->count(),
                'members' => count($members),
            ],
            'projects' => $projects,
            'members' => $members,
            'excluded' => [
                'minutes' => (int) $owners->sum('minutes'),
                'hours' => round($owners->sum('minutes') / 60, 1),
                'entries' => $owners->count(),
                'included' => $includeOwner,
            ],
        ];
    }

    private function share(int $minutes, int $total): float
    {
        return $total > 0 ? round($minutes / $total * 100, 1) : 0.0;
    }
}
