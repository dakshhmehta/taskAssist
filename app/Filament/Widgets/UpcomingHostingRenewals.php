<?php

namespace App\Filament\Widgets;

use App\Models\Hosting;
use Carbon\Carbon;
use Filament\Tables\Actions\Action;
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
        $today = now()->startOfDay();

        $upcomingRenewalDate = Hosting::query()
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '>=', now()->endOfDay())
            ->excludeIgnored()
            ->active()
            ->min('expiry_date');

        $expiredHostingsExist = Hosting::query()
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', $today)
            ->excludeIgnored()
            ->active()
            ->exists();

        $heading = 'Expired Hosting Renewals';
        $hostingsQuery = Hosting::query()
            ->whereNotNull('expiry_date')
            ->excludeIgnored()
            ->active();

        if ($expiredHostingsExist) {
            $hostingsQuery->where('expiry_date', '<', $today)
                ->orderBy('expiry_date', 'ASC');
        } else {
            if ($upcomingRenewalDate) {
                $upcomingRenewalDate = Carbon::parse($upcomingRenewalDate)->endOfDay();
                $heading = 'Hosting Renewals - ' . $upcomingRenewalDate->format('d-m-Y');

                $hostingsQuery->where('expiry_date', '<=', $upcomingRenewalDate->format('Y-m-d H:i:s'))
                    ->where('expiry_date', '>=', '2024-08-01 00:00:00')
                    ->orderBy('expiry_date', 'ASC');
            } else {
                $heading = 'Hosting Renewals';
                $hostingsQuery->whereRaw('1 = 0');
            }
        }

        return $table
            ->heading($heading)
            ->query($hostingsQuery)
            ->columns([
                TextColumn::make('domain')
                    ->label('Domain')
                    ->description(function (Hosting $hosting) use ($today) {
                        $expiryDate = optional($hosting->expiry_date)?->format(config('app.date_format'));

                        if (! $hosting->expiry_date) {
                            return $expiryDate;
                        }

                        if ($hosting->expiry_date->lt($today)) {
                            $daysOverdue = $hosting->expiry_date->diffInDays($today);

                            return $expiryDate . ' | Expired ' . $daysOverdue . ' day' . ($daysOverdue === 1 ? '' : 's') . ' ago';
                        }

                        return $expiryDate;
                    })
            ])
            ->actions([
                Action::make('doIgnore')
                    ->label('Ignore')
                    ->icon('heroicon-o-x-circle')
                    ->action(fn(Hosting $domain) => $domain->ignore())
                    ->visible(fn(Hosting $domain) => !$domain->isIgnored())
                    ->color('danger'),
            ])
            ->defaultPaginationPageOption(5)
            ->paginated();
    }
}
