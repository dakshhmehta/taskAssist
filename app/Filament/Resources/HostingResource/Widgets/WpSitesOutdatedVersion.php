<?php

namespace App\Filament\Resources\HostingResource\Widgets;

use App\Jobs\DetectSiteJob;
use App\Models\Site;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class WpSitesOutdatedVersion extends BaseWidget
{
    protected int | string | array $columnSpan = 3;

    public function table(Table $table): Table
    {
        $sites = [];
        $_sites = Site::excludeIgnored()->get();

        foreach ($_sites as &$site) {
            $version = $site->getMeta('wp_version', 0);

            if ($version != 0 and version_compare($version, config('wp.min_required_version'), '<=')) {
                $sites[] = $site->id;
            }
        }

        return $table
            ->heading('Sites Having Old Version')
            ->query(
                Site::whereIn('id', $sites)
            )
            ->actions([
                Action::make('checkNow')
                    ->label('Check Site')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    // ->requiresConfirmation()
                    ->action(function (Site $site): void {
                        DetectSiteJobb::dispatch($site);
                    })
            ])
            ->columns([
                TextColumn::make('index')
                    ->label('#')
                    ->rowIndex(),
                TextColumn::make('domain')
                    ->description(fn(Site $site) => $site->getMeta('wp_version'))
            ]);
    }
}
