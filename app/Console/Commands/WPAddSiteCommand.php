<?php

namespace App\Console\Commands;

use App\Models\Site;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class WPAddSiteCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wp:add-site';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add new site for WP Management';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $domain = $this->ask('Enter domain');
        $domain = str_replace('www.', '', $domain);
        $url = rtrim($domain, '/') . '?_pull_profile_data=yes';

        $response = Http::get($url);

        if ($response->successful() or config('app.env') == 'local') {
            // $data = $response->json();
            $data = $this->getData();
            if (isset($data['plugin_version'])) {
                $this->info("Valid domain: $domain");

                $site = Site::where('domain', $domain)->first();

                if (! $site) {
                    $site = Site::create([
                        'domain' => $domain,
                    ]);
                }

                foreach ($data as $key => $value) {
                    $site->setMeta($key, $value);
                }
            }
        } else {
            $this->error('Invalid domain or missing plugin_version key.');
        }
    }

    public function getData()
    {
        $data = '{"url":"https:\/\/shrujan_org.test","active_theme":"Shrujan Child","wp_version":"6.7.2","php_version":"7.4.33","mysql_version":"8.0.33","admin_username":"kunal","admin_email":"rudrika.ri@gmail.com","site_email":"info@shrujan.org","plugin_version":"0.0.1"}';

        return json_decode($data, true);
    }
}
