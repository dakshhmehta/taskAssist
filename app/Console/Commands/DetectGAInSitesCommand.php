<?php

namespace App\Console\Commands;

use App\Jobs\DetectSiteJob;
use App\Models\Hosting;
use App\Models\Site;
use App\Notifications\SiteIsDownNotification;
use App\Notifications\SiteIsUpNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class DetectGAInSitesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sites:detect';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetches Meta Informations from the websites for health monitoring';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $sites = Site::orderBy('updated_at', 'asc')
            ->excludeIgnored()
            ->limit(10)
            ->get();

        foreach ($sites as $site) {
            DetectSiteJob::dispatch($site); // Queue the job
            $this->info("Job dispatched for " . $site->domain);
        }

        return 0;
    }
}
