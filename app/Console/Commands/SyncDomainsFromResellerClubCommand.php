<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Models\Hosting;
use App\ResellerClub;
use Illuminate\Console\Command;

class SyncDomainsFromResellerClubCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-rc';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize resellerclub data';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Domains
        $domains = ResellerClub::getDomains();

        if (is_string($domains)) {
            $this->error($domains);
        }

        $domainTableHeader = ['Domain', 'Expiry Date'];

        $domainTableData = [];
        for ($i = 1; $i <= $domains['recsonpage']; $i++) {
            // Update in database
            $domain = Domain::firstOrCreate([
                'tld' => $domains[$i]['entity.description'],
            ]);
            $domain->expiry_date = date('Y-m-d H:i:s', $domains[$i]['orders.endtime']);
            $domain->save();

            $domainTableData[] = [$domain->tld, $domain->expiry_date->format('d-m-Y')];
        }

        $this->info('Domains');
        $this->table($domainTableHeader, $domainTableData);

        // Hostings
        $hostings = ResellerClub::getHostings('in');

        if (is_string($hostings)) {
            $this->error($hostings);
        }

        $hostingTableHeader = ['Domain', 'Expiry Date'];

        $hostingTableData = [];
        for ($i = 1; $i <= $hostings['recsonpage']; $i++) {
            $hosting = Hosting::firstOrCreate([
                'domain' => $hostings[$i]['entity.description'],
            ]);
            $hosting->expiry_date = date('Y-m-d H:i:s', $hostings[$i]['orders.endtime']);
            $hosting->server = 'rc-in-linux';
            $hosting->save();

            $hostingTableData[] = [$hostings[$i]['entity.description'], date('d-m-Y', $hostings[$i]['orders.endtime'])];
        }

        $this->info('Linux Hostings - India');
        $this->table($hostingTableHeader, $hostingTableData);

        $hostings = ResellerClub::getHostings('us');

        if (is_string($hostings)) {
            $this->error($hostings);
        }

        $hostingTableHeader = ['Domain', 'Expiry Date'];

        $hostingTableData = [];
        for ($i = 1; $i <= $hostings['recsonpage']; $i++) {
            $hosting = Hosting::firstOrCreate([
                'domain' => $hostings[$i]['entity.description'],
            ]);
            $hosting->expiry_date = date('Y-m-d H:i:s', $hostings[$i]['orders.endtime']);
            $hosting->server = 'rc-us-linux';
            $hosting->save();

            $hostingTableData[] = [$hostings[$i]['entity.description'], date('d-m-Y', $hostings[$i]['orders.endtime'])];
        }

        $this->info('Linux Hostings - US');
        $this->table($hostingTableHeader, $hostingTableData);
    }
}
