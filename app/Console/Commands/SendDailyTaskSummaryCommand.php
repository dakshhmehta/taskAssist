<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Carbon;
use App\Mail\DailyTaskSummaryMail;

class SendDailyTaskSummaryCommand extends Command
{
    protected $signature = 'app:send-daily-summary {--date= : The date for which to generate summary (Y-m-d)}';
    protected $description = 'Send daily task summary to users who created or completed tasks today';

    public function handle()
    {
        // Parse date or default to today
        try {
            $inputDate = $this->option('date');

            $today = $inputDate ? Carbon::parse($inputDate)->startOfDay() : now()->startOfDay();
        } catch (\Exception $e) {
            $this->error("Invalid date format. Use Y-m-d.");
            return 1;
        }

        // Get users who created or completed at least 1 task today
        $users = User::whereHas('tasks', function ($query) use ($today) {
            $query->whereDate('created_at', $today)
                ->orWhereDate('completed_at', $today);
        })->get();

        foreach ($users as $user) {
            $createdTasks = $user->tasks()->whereDate('created_at', $today)->get();
            $completedTasks = $user->tasks()->whereDate('completed_at', $today)->get();

            Mail::to($user->email)
                ->cc('admin@romininteractive.com')
                ->send(new DailyTaskSummaryMail($today, $user, $createdTasks, $completedTasks));

            $this->info("Sent daily summary to: " . $user->email);
        }
    }
}
