<?php

namespace App\Filament\Widgets;

use App\Models\Domain;
use Carbon\Carbon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class UpcomingDomainRenewals extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 2;

    public function table(Table $table): Table
    {
        $upcomingRenewalDate = Domain::where('expiry_date', '>=', now()->startOfDay())->min('expiry_date');
        $upcomingRenewalDate = Carbon::parse($upcomingRenewalDate);

        return $table
            ->heading('Domain Renewals - '.$upcomingRenewalDate->format('d-m-Y'))
            ->query(function() use(&$upcomingRenewalDate) {

                    $domains = Domain::where('expiry_date', 'LIKE', $upcomingRenewalDate->format('Y-m-d').'%');
    
                    return $domains;
                }
            )
            ->columns([
                TextColumn::make('tld') 
                    ->label('Domain')
            ])
            ->paginated(false);
    }
}
