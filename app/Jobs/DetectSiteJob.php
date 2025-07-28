<?php

namespace App\Jobs;

use App\Models\Site;
use App\Notifications\SiteIsDownNotification;
use App\Notifications\SiteIsUpNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DetectSiteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Site $site;

    public function __construct(Site $site)
    {
        $this->site = $site;
    }

    public function handle()
    {
        $site = $this->site;
        $url = $site->domain;

        $oldIsDown = $site->getMeta('is_down');

        try {
            $response = Http::get($url);
            $site->touch();

            if (!$response->successful()) {
                throw new \Exception("HTTP request failed: " . $response->status());
            }

            $html = $response->body();

            $this->checkDowntime($site, $html);
            $this->detectGA($site, $html);
            $this->detectWPVersion($site, $html);

        } catch (\Exception $e) {
            $site->setMeta('is_down', true);
            $site->setMeta('down_remarks', $e->getMessage());
        }

        $this->notifyTelegram($site, $oldIsDown);
    }

    protected function notifyTelegram(Site $site, $oldIsDown)
    {
        $isDown = $site->getMeta('is_down');
        if ($oldIsDown !== $isDown) {
            $site->notify($isDown ? new SiteIsDownNotification() : new SiteIsUpNotification());
        }
    }

    protected function checkDowntime($site, $html)
    {
        $keywords = [
            'site is down', 'error 500', 'not reachable',
            'bad gateway', '502 bad gateway', '503 service unavailable',
            'database error', 'This Account has been suspended',
            'Error establishing a database connection',
        ];

        $lowerHtml = strtolower($html);
        foreach ($keywords as $keyword) {
            if (strpos($lowerHtml, strtolower($keyword)) !== false) {
                $site->setMeta('is_down', true);
                $site->setMeta('down_remarks', "Found keyword: {$keyword}");
                return;
            }
        }

        $site->setMeta('is_down', false);
        $site->setMeta('down_remarks', null);
    }

    protected function detectGA($site, $html)
    {
        preg_match_all('/\b(UA-\d{4,10}(?:-\d{1,4})?|G-[A-Z0-9]{8,}|GT-[A-Z0-9]{7})\b/', $html, $matches);
        $gaId = $matches[0][0] ?? null;
        $site->setMeta('ga_id', $gaId);
    }

    protected function detectWPVersion($site, $html)
    {
        if (preg_match('/<meta\s+name=["\']generator["\']\s+content=["\']WordPress\s+([\d.]+)["\']\s*\/?>/i', $html, $matches)) {
            $site->setMeta('wp_version', $matches[1]);
        } else {
            $site->setMeta('wp_version', null);
        }
    }
}