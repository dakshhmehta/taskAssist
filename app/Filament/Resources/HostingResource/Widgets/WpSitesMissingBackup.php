<?php

namespace App\Filament\Resources\HostingResource\Widgets;

use App\Models\Site;
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
                Site::noLatestBackup()
            )
            ->columns([
                TextColumn::make('index')
                    ->label('#')
                    ->rowIndex(),
                TextColumn::make('domain')
                ->description(fn(Site $site) => $site->getMeta('last_backup', 'Unknown'))
            ]);
    }
}
