<?php

namespace App\Filament\Widgets;

use App\Models\Domain;
use Carbon\Carbon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;

class UpcomingDomainRenewals extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 3;

    public static function canView(): bool
    {
        return Auth::user()->is_admin;
    }

    public function table(Table $table): Table
    {
        $upcomingRenewalDate = Domain::where('expiry_date', '>=', now()->endOfDay())->min('expiry_date');
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
