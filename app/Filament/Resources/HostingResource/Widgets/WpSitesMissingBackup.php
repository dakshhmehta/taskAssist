<?php

namespace App\Filament\Resources\HostingResource\Widgets;

use App\Jobs\DetectSiteJob;
use App\Models\Site;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class WpSitesMissingBackup extends BaseWidget
{
    protected int | string | array $columnSpan = 3;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Sites Having Old Backup')
            ->query(
                Site::noLatestBackup()->excludeIgnored()
            )
            ->actions([
                Action::make('checkNow')
                    ->label('Check Site')
                    ->icon('heroicon-o-arrow-path')
                    ->color('primary')
                    // ->requiresConfirmation()
                    ->action(function (Site $site): void {
                        DetectSiteJob::dispatch($site);
                    })
            ])
            ->columns([
                TextColumn::make('index')
                    ->label('#')
                    ->rowIndex(),
                TextColumn::make('domain')
                ->description(fn(Site $site) => $site->getMeta('last_backup', 'Unknown'))
            ]);
    }
}
