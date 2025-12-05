<?php

namespace App\Console\Commands;

use App\Models\Timesheet;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ListWeeklyPerformersCommand extends Command
{
    protected $signature = 'app:weekly-performers {start-date}';
    protected $description = 'Lists weekly performers from start date to current week';

    public function handle()
    {
        $startDate = Carbon::parse($this->argument('start-date'));
        $endWeek = Carbon::now()->weekOfYear;
        
        $users = User::all();
        $heading = ['Week', '#', 'User', 'Time Worked', 'Time Base Performance', 'Task Base Performance', 'Performance'];
        
        $startWeek = $startDate->weekOfYear;
        $year = $startDate->year;
        $userStars = [];
        
        foreach ($users as $user) {
            $userStars[$user->id] = [];
        }
        
        for ($week = $startWeek; $week <= $endWeek; $week++) {
            $weekOffset = Carbon::now()->weekOfYear - $week;
            
            $weekStart = Carbon::now()->setISODate($year, $week)->startOfWeek();
            $weekEnd = Carbon::now()->setISODate($year, $week)->endOfWeek();
            
            $this->warn("Week {$week} ({$year}) - {$weekStart->format('M d')} to {$weekEnd->format('M d')}");

            foreach ($users as $user) {
                $user->week_performance = (float) $user->performanceThisWeek($weekOffset * -1);
                $user->week_time_worked = $user->timeWorkedThisWeek($weekOffset * -1);
                $user->time_base_performance = (float) $user->performanceThisWeekTimeBased($weekOffset * -1);
                $user->task_base_performance = (float) $user->performanceThisWeekTaskBased($weekOffset * -1);
            }
            
            $sortedUsers = $users->sortByDesc(fn($user) => [$user->week_performance, $user->week_time_worked]);
            
            // Track weekly winner
            if ($sortedUsers->isNotEmpty()) {
                $winner = $sortedUsers->first();
                $userStars[$winner->id][] = $weekOffset;
            }
            
            $data = [];
            foreach ($sortedUsers as $i => $user) {
                $data[] = [
                    $week,
                    $i + 1,
                    $user->name,
                    Timesheet::toHMS($user->week_time_worked),
                    $user->time_base_performance,
                    $user->task_base_performance,
                    $user->week_performance
                ];
                break;
            }
            
            $this->table($heading, $data);
            $this->line('');
        }
        
        // Summary
        $this->warn('SUMMARY');
        
        $summaryData = [];
        foreach ($users as $user) {
            $summaryData[] = [
                $user->name,
                $user->stars($startDate),
                count($userStars[$user->id]).' '.implode(', ', $userStars[$user->id]),
            ];
        }
        
        usort($summaryData, fn($a, $b) => $b[1] <=> $a[1]);
        
        $this->table(['User', 'Stars Won', 'Total Weeks'], $summaryData);
    }
}