<?php

namespace App\Filament\Widgets;

use App\Models\Hosting;
use Carbon\Carbon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class UpcomingHostingRenewals extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 3;

    public function table(Table $table): Table
    {
        $upcomingRenewalDate = Hosting::where('expiry_date', '>=', now()->startOfDay())->min('expiry_date');
        $upcomingRenewalDate = Carbon::parse($upcomingRenewalDate);

        return $table
            ->heading('Hosting Renewals - '.$upcomingRenewalDate->format('d-m-Y'))
            ->query(function() use(&$upcomingRenewalDate) {
                    $hostings = Hosting::where('expiry_date', 'LIKE', $upcomingRenewalDate->format('Y-m-d').'%');
    
                    return $hostings;
                }
            )
            ->columns([
                TextColumn::make('domain') 
                    ->label('Domain')
            ])
            ->paginated(false);
    }
}
