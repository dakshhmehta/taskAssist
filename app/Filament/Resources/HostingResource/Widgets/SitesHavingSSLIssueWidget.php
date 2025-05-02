<?php

namespace App\Filament\Resources\HostingResource\Widgets;

use App\Models\Hosting;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class SitesHavingSSLIssueWidget extends BaseWidget
{
    protected int | string | array $columnSpan = 3;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Sites Having SSL Issues')
            ->query(
                Hosting::excludeIgnored()
                    ->whereNull('ssl_expiry_date')
                    ->orWhere('ssl_expiry_date', '<=', now()->format('Y-m-d'))
            )
            ->columns([
                TextColumn::make('domain')
                    ->description(fn(Hosting $domain) => optional($domain->ssl_expiry_date)->format(config('app.date_format'))),
            ])
            ->actions([
                // Action::make('doIgnore')
                //     ->label('Ignore')
                //     ->icon('heroicon-o-x-circle')
                //     ->action(fn(Hosting $domain) => $domain->ignore())
                //     ->visible(fn(Hosting $domain) => !$domain->isIgnored())
                //     ->color('danger'),

            ]);
    }
}
