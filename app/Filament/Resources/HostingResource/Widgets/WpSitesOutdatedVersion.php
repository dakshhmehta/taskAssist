<?php

namespace App\Filament\Resources\HostingResource\Widgets;

use App\Models\Site;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class WpSitesOutdatedVersion extends BaseWidget
{
    protected int | string | array $columnSpan = 3;

    public function table(Table $table): Table
    {
        $sites = [];
        $_sites = Site::all();

        foreach($_sites as &$site){
            $version = $site->getMeta('wp_version', 0);

            if($version != 0 and version_compare($version, config('wp.min_required_version'), '<=')){
                $sites[] = $site->id;
            }
        }

        return $table
            ->heading('Sites Having Old Version')
            ->query(
                Site::whereIn('id', $sites)
            )
            ->paginated(false)
            ->columns([
                TextColumn::make('domain')
                    ->description(fn(Site $site) => $site->getMeta('wp_version'))
            ]);
    }
}
