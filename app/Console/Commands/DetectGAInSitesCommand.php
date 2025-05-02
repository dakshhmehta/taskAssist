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
        $sites = Site::orderBy('updated_at', 'asc')->get();

        foreach ($sites as &$site) {
            try {
                $url = $site->domain;
                $response = Http::get($url);

                $this->info('Detecting for ' . $url);

                if (!$response->successful()) {
                    $this->error("Failed to fetch the URL. Status: " . $response->status());
                    continue;
                }

                $html = $response->body();

                $this->detectGA($site, $html);
                $this->detectWPVersion($site, $html);
            } catch (\Exception $e) {
                $this->error("Error: " . $e->getMessage());
                continue;
            }
        }


        return 0;
    }

    public function detectWPVersion($site, $html)
    {
        if (preg_match('/<meta\s+name=["\']generator["\']\s+content=["\']WordPress\s+([\d.]+)["\']\s*\/?>/i', $html, $matches)) {
            $version = $matches[1];
            $this->info('WP Version ' . $version);

            $site->setMeta('wp_version', $version);
        } else {
            $this->warn('WP Version not found.');

            $site->setMeta('wp_version', null);
        }
    }

    public function detectGA($site, $html)
    {
        // Match UA-XXXXX-Y, G-XXXXXXXXXX, GT-XXXXXXXX
        preg_match_all('/\b(UA-\d{4,10}(?:-\d{1,4})?|G-[A-Z0-9]{8,}|GT-[A-Z0-9]{7})\b/', $html, $matches);

        $gaId = null;
        if (!empty($matches[0])) {
            $gaId = array_unique($matches[0])[0];

            $this->info("Found GA Property IDs:" . $gaId);

            $site->setMeta('ga_id', $gaId);
        } else {
            $this->warn("No GA Property ID.");

            $site->setMeta('ga_id', null);
        }
    }
}
