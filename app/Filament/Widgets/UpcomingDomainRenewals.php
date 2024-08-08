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
        $upcomingRenewalDate = Carbon::parse($upcomingRenewalDate)->endOfDay();

        return $table
            ->heading('Domain Renewals - ' . $upcomingRenewalDate->format('d-m-Y'))
            ->query(
                function () use (&$upcomingRenewalDate) {
                    $domains = Domain::where('expiry_date', '<=', $upcomingRenewalDate->format('Y-m-d H:i:s'))
                        ->where('expiry_date', '>=', '2024-08-01 00:00:00');

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
