<?php

namespace App\Console\Commands;

use App\Models\Domain;
use Illuminate\Console\Command;

class AddRCDomainCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rc:add-domain';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add Domain from ResellerClub';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $domains = $this->ask('What domains do you want to sync? (, seperated)');

        $domains = explode(',', $domains);

        foreach ($domains as $domain) {
            $domain = Domain::firstOrCreate([
                'tld' => $domain,
            ]);
            $domain->sync();
        }
    }
}
