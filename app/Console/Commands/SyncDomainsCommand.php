<?php

namespace App\Console\Commands;

use App\Models\Domain;
use Illuminate\Console\Command;

class SyncDomainsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'domains:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize every domain (including ignored ones) from ResellerClub';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Include ignored domains so ones that were ignored due to a failed
        // sync can be recovered once the ResellerClub API returns valid data.
        $domains = Domain::orderBy('tld')->get();

        $this->info('Syncing ' . $domains->count() . ' domain(s) (including ignored)...');

        $synced = [];
        $failed = [];
        $unignored = 0;

        foreach ($domains as $domain) {
            $wasIgnored = $domain->isIgnored();

            try {
                $domain->sync();

                $domain->unIgnore();

                if ($wasIgnored) {
                    $unignored++;
                }

                $synced[] = [$domain->tld, optional($domain->expiry_date)->format('d-m-Y')];
            } catch (\Throwable $e) {
                $domain->ignore();

                $failed[] = [$domain->tld, $e->getMessage()];

                $this->error('Unable to sync domain ' . $domain->tld . ': ' . $e->getMessage());
            }
        }

        $this->info('Domains - Synced');
        $this->table(['Domain', 'Expiry Date'], $synced);

        if (! empty($failed)) {
            $this->warn('Domains - Failed');
            $this->table(['Domain', 'Error'], $failed);
        }

        $this->info(sprintf(
            '%d domain(s) synced, %d unignored, %d failed.',
            count($synced),
            $unignored,
            count($failed),
        ));

        return self::SUCCESS;
    }
}
