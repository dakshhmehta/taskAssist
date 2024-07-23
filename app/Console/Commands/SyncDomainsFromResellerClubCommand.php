<?php

namespace App\Console\Commands;

use App\Models\Domain;
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
        $domains = ResellerClub::getDomains();

        if (is_string($domains)) {
            $this->error($domains);
        }

        $domainTableHeader = ['Domain', 'Expiry Date'];

        $domainTableData = [];
        for($i = 1; $i <= $domains['recsonpage']; $i++){
            // Update in database
            $domain = Domain::firstOrCreate([
                'tld' => $domains[$i]['entity.description'],
            ]);
            $domain->expiry_date = date('Y-m-d H:i:s', $domains[$i]['orders.endtime']);
            $domain->save();

            $domainTableData[] = [$domain->tld, $domain->expiry_date->format('d-m-Y')];
        }

        $this->table($domainTableHeader, $domainTableData);
    }
}
