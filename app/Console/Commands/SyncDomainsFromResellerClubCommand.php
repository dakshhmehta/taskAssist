<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Models\Hosting;
use App\ResellerClub;
use App\WHM;
use Carbon\Carbon;
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
        $this->getDomains();

        // Hostings
        $this->getLinuxHostingsIN();
        $this->getLinuxHostingsUS();

        $this->getWHMHostings('romin');
        $this->getWHMHostings('dristal');
    }

    public function getDomains()
    {
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
    }

    public function getWHMHostings($server)
    {
        $whm = new WHM();
        $whm->server($server);

        $hostings = $whm->listAccounts();

        if (is_string($hostings)) {
            $this->error($hostings);
        }

        $hostingTableHeader = ['Domain', 'Expiry Date'];

        $hostingTableData = [];
        for ($i = 0; $i < count($hostings); $i++) {
            $hosting = Hosting::firstOrCreate([
                'domain' => $hostings[$i]['domain'],
            ]);

            if (! $hosting->expiry_date) {
                // Only update expiry date if record is being created and hence does not have default expiry date
                $hosting->expiry_date = Carbon::parse($hostings[$i]['startdate'])->setYear(date('Y'));
            }
            if ($hostings[$i]['suspended'] == 1) {
                $hosting->suspended_at = now();
            }
            else {
                $hosting->suspended_at = null;
            }

            $hosting->server = $server;
            $hosting->save();

            $hostingTableData[] = [$hosting->domain, $hosting->expiry_date];
        }

        $this->info('Linux Hostings - ' . $server);
        $this->table($hostingTableHeader, $hostingTableData);
    }

    public function getLinuxHostingsIN()
    {
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
    }

    public function getLinuxHostingsUS()
    {
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
