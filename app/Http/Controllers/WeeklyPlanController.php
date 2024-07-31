<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;

class WeeklyPlanController extends Controller
{
    public function get(Request $request){
        $startDate = now();

        while(! $startDate->isMonday()){
            $startDate = $startDate->addDay();
        }

        $users = User::all();

        foreach($users as &$user){
            $user->_performance = (float) $user->performanceThisWeek();
            $user->_time_worked = $user->timeWorkedThisWeek();
        }

        $starPerformer = $users->sortByDesc('_performance', false)
            ->sortByDesc('_time_worked', false)
            ->first();

        $endDate = (clone $startDate)->addDay(4);

        $tasks = Task::where('due_date', '>=', $startDate->startOfDay()->format('Y-m-d H:i:s'))
                ->where('due_date', '<=', $endDate->startOfDay()->format('Y-m-d H:i:s'))
                ->whereNotNull('due_date')
                ->whereNull('completed_at')
                ->orderBy('assignee_id')
                ->orderBy('due_date', 'ASC')->get();

        return view('weekly_sheet', compact('tasks', 'startDate', 'endDate', 'starPerformer'));
    }

    public function getStandupsheet(Request $request){
        $startDate = now();

        $tasks = Task::where('due_date', '>=', $startDate->startOfDay()->format('Y-m-d H:i:s'))
                ->where('due_date', '<=', $startDate->startOfDay()->format('Y-m-d H:i:s'))
                ->whereNotNull('due_date')
                ->whereNull('completed_at')
                ->orderBy('assignee_id')
                ->orderBy('due_date', 'ASC')->get();

        return view('daily_sheet', compact('tasks', 'startDate'));
    }
}
