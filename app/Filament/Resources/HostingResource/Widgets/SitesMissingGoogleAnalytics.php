<?php

namespace App\Filament\Resources\HostingResource\Widgets;

use App\Models\Site;
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
                Site::whereDoesntHave('meta', function($q){
                    $q->where('key', 'ga_id');
                })
            )
            ->columns([
                TextColumn::make('domain'),
            ]);
    }
}
