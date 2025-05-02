<?php

namespace App\Console\Commands;

use App\Models\Hosting;
use App\Models\Site;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class DetectGAInSitesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sites:detect-ga';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetches Google Analytics ID from the website';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $sites = Site::orderBy('updated_at', 'asc')->get();

        foreach ($sites as &$site) {
            $url = $site->domain;

            try {
                $response = Http::get($url);

                if (!$response->successful()) {
                    $this->error("Failed to fetch the URL. Status: " . $response->status());
                    continue;
                }

                $html = $response->body();

                // Match UA-XXXXX-Y, G-XXXXXXXXXX, GT-XXXXXXXX
                preg_match_all('/\b(UA-\d{4,10}(?:-\d{1,4})?|G-[A-Z0-9]{8,}|GT-[A-Z0-9]{7})\b/', $html, $matches);

                $this->info('Detecting GA for '.$url);

                $gaId = null;
                if (!empty($matches[0])) {
                    $gaId = array_unique($matches[0])[0];

                    $this->info("Found GA Property IDs:" . $gaId);

                    $site->setMeta('ga_id', $gaId);
                } else {
                    $this->warn("No GA Property ID.");

                    $site->setMeta('ga_id', null);
                }
            } catch (\Exception $e) {
                $this->error("Error: " . $e->getMessage());
            }
        }


        return 0;
    }
}
