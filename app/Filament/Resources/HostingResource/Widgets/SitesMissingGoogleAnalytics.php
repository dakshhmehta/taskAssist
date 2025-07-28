<?php

namespace App\Filament\Resources\HostingResource\Widgets;

use App\Jobs\DetectSiteJob;
use App\Models\Site;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class SitesMissingGoogleAnalytics extends BaseWidget
{
    protected int | string | array $columnSpan = 3;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Missing Google Analytics')
            ->query(
                Site::whereDoesntHave('meta', function ($q) {
                    $q->where('key', 'ga_id');
                })
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
                TextColumn::make('domain'),
            ]);
    }
}
