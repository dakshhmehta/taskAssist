<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\Request;

class WeeklyPlanController extends Controller
{
    public function get(Request $request){
        $startDate = now();

        while(! $startDate->isMonday()){
            $startDate = $startDate->addDay();
        }

        $endDate = (clone $startDate)->addDay(4);

        $tasks = Task::where('due_date', '>=', $startDate->startOfDay()->format('Y-m-d H:i:s'))
                ->where('due_date', '<=', $endDate->startOfDay()->format('Y-m-d H:i:s'))
                ->whereNotNull('due_date')
                ->whereNull('completed_at')
                ->orderBy('assignee_id')
                ->orderBy('due_date', 'ASC')->get();

        return view('weekly_sheet', compact('tasks', 'startDate', 'endDate'));
    }
}
