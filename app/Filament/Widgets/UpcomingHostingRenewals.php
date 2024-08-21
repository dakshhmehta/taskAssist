<?php

namespace App\Filament\Widgets;

use App\Models\Hosting;
use Carbon\Carbon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;

class UpcomingHostingRenewals extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 3;

    public static function canView(): bool
    {
        return Auth::user()->is_admin;
    }

    public function table(Table $table): Table
    {
        $upcomingRenewalDate = Hosting::where('expiry_date', '>=', now()->endOfDay())->min('expiry_date');
        $upcomingRenewalDate = Carbon::parse($upcomingRenewalDate)->endOfDay();

        return $table
            ->heading('Hosting Renewals - ' . $upcomingRenewalDate->format('d-m-Y'))
            ->query(
                function () use (&$upcomingRenewalDate) {
                    $hostings = Hosting::where('expiry_date', '<=', $upcomingRenewalDate->format('Y-m-d H:i:s'))
                        ->where('expiry_date', '>=', '2024-08-01 00:00:00')->active();

                    return $hostings;
                }
            )
            ->columns([
                TextColumn::make('domain')
                    ->label('Domain')
                    ->description(fn(Hosting $hosting) => optional($hosting->expiry_date)->format(config('app.date_format')))
            ])
            ->defaultPaginationPageOption(5)
            ->paginated();
    }
}
