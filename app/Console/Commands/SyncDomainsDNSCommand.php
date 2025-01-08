<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Models\Hosting;
use Illuminate\Console\Command;

class SyncDomainsDNSCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-dns';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch DNS of all registered domains with us';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $domains = Hosting::
            orderBy('domain', 'asc')
            ->get();

        $data = [];

        foreach ($domains as $i => $domain) {
            $records = dns_get_record($domain->domain, DNS_NS);

            if (!empty($records)) {
                foreach ($records as $record) {
                    $hasHosting = Hosting::where('domain', $domain->domain)
                        ->where('server', 'romin')
                        ->exists();
                    if ($record['type'] == 'NS' && $hasHosting) {
                        if (strpos($record['target'], 'seodns') > 0 || strpos($record['target'], 'romin.in') > 0) {
                            $data[$i]['i'] = $i + 1;
                            $data[$i]['domain'] = $domain->domain;
                            $data[$i++]['ns'] = $record['target'];
                        }
                    }
                }
            }
        }

        $this->table(['#', 'Domain', 'NS'], $data);
    }
}
