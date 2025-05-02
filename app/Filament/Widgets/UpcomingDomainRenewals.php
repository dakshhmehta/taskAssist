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
        $upcomingRenewalDate = Domain::where('expiry_date', '>=', now()->endOfDay())->min('expiry_date');
        $upcomingRenewalDate = Carbon::parse($upcomingRenewalDate)->endOfDay();

        return $table
            ->heading('Domain Renewals - ' . $upcomingRenewalDate->format('d-m-Y'))
            ->query(
                function () use (&$upcomingRenewalDate) {
                    $domains = Domain::where('expiry_date', '<=', $upcomingRenewalDate->format('Y-m-d H:i:s'))
                        ->where('expiry_date', '>=', '2024-08-01 00:00:00')->excludeIgnored();

                    return $domains;
                }
            )
            ->columns([
                TextColumn::make('index')
                    ->label('#')
                    ->rowIndex(),
                TextColumn::make('tld')
                    ->label('Domain')
                    ->description(fn(Domain $domain) => optional($domain->expiry_date)->format(config('app.date_format')))
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
