<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserLeave;
use Illuminate\Console\Command;

class CreditTeamCLCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:credit-team-cl {--sync-debit}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Credits the CL for the team at the start of the month since start of the year (if not already done)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // For each month, from the beginning of the year, credit the CL on the start date of month, if not already done
        $startOfYear = now()->startOfYear();
        $users = User::all();

        while ($startOfYear->lte(now())) {
            $date = $startOfYear->startOfMonth();

            foreach ($users as $user) {
                $credited = $user->creditCLForMonth($date);
                if ($credited) {
                    $this->info('Credited CL for ' . $user->name . ' for ' . $date->format('m, Y'));
                }
            }

            $date->addMonth();
        }

        if($this->option('sync-debit')){
            $userLeaves = UserLeave::where('code', 'CL')
                ->where('from_date', '>=', $startOfYear)
                ->where('status', 'APPROVED')
                ->get();
    
            foreach ($userLeaves as $userLeave) {
                $userLeave->touch();
            }

            $this->info('User leave debit balance synced');
        }

    }
}
