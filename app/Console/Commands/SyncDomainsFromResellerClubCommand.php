<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Models\Email;
use App\Models\Hosting;
use App\Models\HostingPackage;
use App\Models\Site;
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
        $this->getDomains('new');

        // Get Gsuite
        // $this->getGsuites();

        // // Hostings
        // $this->getLinuxHostingsIN();
        // $this->getLinuxHostingsUS();

        // $this->getWHMHostings('romin');
        // $this->getWHMHostings('dristal');
    }

    public function getGSuites()
    {
        $accounts = ResellerClub::getGSuites();

        $tableHeader = ['Domain', 'Expiry Date', '# of Accounts'];
        $tableData = [];

        for ($i = 1; $i <= $accounts['recsonpage']; $i++) {
            // Update in database
            $email = Email::firstOrCreate([
                'domain' => $accounts[$i]['entity.description'],
                'provider' => 'gappsin',
            ]);
            $email->accounts_count = $accounts[$i]['accounts_count'];
            $email->expiry_date = date('Y-m-d H:i:s', $accounts[$i]['orders.endtime']);
            $email->save();

            $tableData[] = [$email->domain, $email->expiry_date->format('d-m-Y'), $email->accounts_count];
        }

        $this->table($tableHeader, $tableData);
    }

    public function getDomains($mode = 'expiring')
    {
        $domains = ResellerClub::getDomains($mode);

        if (is_string($domains)) {
            $this->error($domains);
        }

        $domainTableHeader = ['Domain', 'Expiry Date'];

        $_tlds = [];

        $domainTableData = [];
        for ($i = 1; $i <= $domains['recsonpage']; $i++) {
            // Update in database
            $domain = Domain::firstOrCreate([
                'tld' => $domains[$i]['entity.description'],
            ]);
            $domain->expiry_date = date('Y-m-d H:i:s', $domains[$i]['orders.endtime']);
            $domain->save();

            $_tlds[] = $domain->tld;

            $domainTableData[] = [$domain->tld, $domain->expiry_date->format('d-m-Y')];
        }

        $this->info('Domains - '.$mode);
        $this->table($domainTableHeader, $domainTableData);

        $domains = Domain::whereNotIn('tld', $_tlds)
            ->excludeIgnored()
            ->get();

        $domainTableData = [];

        foreach ($domains as &$domain) {
            try {
                $domain->sync();

                $domain->unIgnore();

                $domainTableData[] = [$domain->tld, $domain->expiry_date->format('d-m-Y')];
            } catch (\Exception $e) {
                $domain->ignore();
                $this->error('Unable to sync domain ' . $domain->tld);
            }
        }

        $this->info('Domains - Renewed');
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

            if ($hosting->domainLink) {
                $hosting->expiry_date = $hosting->domainLink->expiry_date;
            } elseif (! $hosting->expiry_date) {
                // Only update expiry date if record is being created and hence does not have default expiry date
                $hosting->expiry_date = Carbon::parse($hostings[$i]['startdate'])->setYear(date('Y'));
            }
            if ($hostings[$i]['suspended'] == 1) {
                $hosting->suspended_at = now();
            } else {
                $hosting->suspended_at = null;
            }

            // TODO: Sync the renewal date to domain renewal date

            $hosting->package_id = $this->getHostingPackage($hostings[$i]);
            $hosting->server = $server;

            $hosting->client_id = $hosting->getLastInvoice()?->client_id;

            $hosting->save();

            Site::firstOrCreate(['domain' => 'https://' . $hosting->domain]);

            $hostingTableData[] = [$hosting->domain, $hosting->expiry_date];
        }

        $this->info('Linux Hostings - ' . $server);
        $this->table($hostingTableHeader, $hostingTableData);
    }

    public function getHostingPackage($hosting)
    {
        $storage = str_replace('M', '', $hosting['disklimit']);

        $storage = (($storage == 'unlimited') ? -1 : $storage);

        $package = HostingPackage::where('storage', $storage)->first();

        if (! $package) {
            $package = HostingPackage::create([
                'storage' => $storage,
                'price' => 0,
            ]);
        }

        $package->emails = (($hosting['maxpop'] == 'unlimited') ? -1 : $hosting['maxpop']);
        $package->save();

        return $package->id;
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

            $hosting->client_id = $hosting->getLastInvoice()?->client_id;

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

            $hosting->client_id = $hosting->getLastInvoice()?->client_id;

            $hosting->save();

            $hostingTableData[] = [$hostings[$i]['entity.description'], date('d-m-Y', $hostings[$i]['orders.endtime'])];
        }

        $this->info('Linux Hostings - US');
        $this->table($hostingTableHeader, $hostingTableData);
    }
}
