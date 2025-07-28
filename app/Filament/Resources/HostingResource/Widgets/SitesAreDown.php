<?php

namespace App\Filament\Resources\HostingResource\Widgets;

use App\Jobs\DetectSiteJob;
use App\Models\Site;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class SitesAreDown extends BaseWidget
{
    protected int | string | array $columnSpan = 6;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Sites Down')
            ->query(
                Site::hasMeta('is_down', true)->excludeIgnored()
            )
            ->actions([
                Action::make('checkNow')
                    ->label('Check Site')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    // ->requiresConfirmation()
                    ->action(function (Site $site): void {
                        DetectSiteJob::dispatch($site);
                    }),
                Action::make('doIgnore')
                    ->label('Ignore')
                    ->icon('heroicon-o-x-circle')
                    ->action(fn(Site $domain) => $domain->ignore())
                    ->visible(fn(Site $domain) => !$domain->isIgnored())
                    ->color('danger'),
            ])
            ->recordUrl(fn(Site $site) => $site->domain)
            ->columns([
                TextColumn::make('index')
                    ->label('#')
                    ->rowIndex(),
                TextColumn::make('domain')
                    ->extraAttributes(['class' => 'w-64 whitespace-normal break-words'])
                    ->description(fn(Site $site) => $site->getMeta('down_remarks')),
            ]);
    }
}
