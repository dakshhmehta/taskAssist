<?php

namespace App\Filament\Widgets;

use App\Models\Domain;
use Carbon\Carbon;
use Filament\Tables\Actions\Action;
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
        $today = now()->startOfDay();
        $expiredDomainsExist = Domain::query()
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', $today)
            ->excludeIgnored()
            ->exists();

        $heading = 'Expired Domain Renewals';
        $domainsQuery = Domain::query()
            ->whereNotNull('expiry_date')
            ->excludeIgnored();

        if ($expiredDomainsExist) {
            $domainsQuery->where('expiry_date', '<', $today)
                ->orderBy('expiry_date', 'ASC');
        } else {
            $upcomingRenewalDate = Domain::query()
                ->whereNotNull('expiry_date')
                ->where('expiry_date', '>=', now()->endOfDay())
                ->excludeIgnored()
                ->min('expiry_date');

            if ($upcomingRenewalDate) {
                $upcomingRenewalDate = Carbon::parse($upcomingRenewalDate)->endOfDay();
                $heading = 'Domain Renewals - ' . $upcomingRenewalDate->format('d-m-Y');

                $domainsQuery->where('expiry_date', '<=', $upcomingRenewalDate->format('Y-m-d H:i:s'))
                    ->where('expiry_date', '>=', '2024-08-01 00:00:00')
                    ->orderBy('expiry_date', 'ASC');
            } else {
                $heading = 'Domain Renewals';
                $domainsQuery->whereRaw('1 = 0');
            }
        }

        return $table
            ->heading($heading)
            ->query($domainsQuery)
            ->columns([
                TextColumn::make('index')
                    ->label('#')
                    ->rowIndex(),
                TextColumn::make('tld')
                    ->label('Domain')
                    ->description(function (Domain $domain) use ($today) {
                        $expiryDate = optional($domain->expiry_date)?->format(config('app.date_format'));

                        if (! $domain->expiry_date) {
                            return $expiryDate;
                        }

                        if ($domain->expiry_date->lt($today)) {
                            $daysOverdue = $domain->expiry_date->diffInDays($today);

                            return $expiryDate . ' | Expired ' . $daysOverdue . ' day' . ($daysOverdue === 1 ? '' : 's') . ' ago';
                        }

                        return $expiryDate;
                    })
            ])
            ->actions([
                Action::make('doIgnore')
                    ->label('Ignore')
                    ->icon('heroicon-o-x-circle')
                    ->action(fn(Domain $domain) => $domain->ignore())
                    ->visible(fn(Domain $domain) => !$domain->isIgnored())
                    ->color('danger'),
            ])
            ->defaultPaginationPageOption(5)
            ->paginated();
    }
}
