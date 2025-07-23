<?php

namespace App\Filament\Resources\HostingResource\Widgets;

use App\Models\Site;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class SitesAreDown extends BaseWidget
{
    protected int | string | array $columnSpan = 3;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Sites Down')
            ->query(
                Site::hasMeta('is_down', true)
            )
            ->columns([
                TextColumn::make('index')
                    ->label('#')
                    ->rowIndex(),
                TextColumn::make('domain')
                    ->description(fn(Site $site) => $site->getMeta('down_remarks')),
            ]);
    }
}
