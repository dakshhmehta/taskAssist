<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ClientResource\Widgets\ClientReceivablesTable;
use App\Filament\Resources\UserResource;
use App\Models\Client;
use Filament\Pages\Page;

class AccountingDashboard extends Page
{
    protected static ?int $navigationSort = 1;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.accounting-dashboard';
    protected static ?string $navigationGroup = 'Reports'; // Group it logically

    public function getWidgets(): array
    {
        // Update client receivables
        $clients = Client::all();

        foreach ($clients as $client) {
            $client->touch();
        }

        return [
            ClientReceivablesTable::make(),
        ];
    }

    public function getVisibleWidgets(): array
    {
        return $this->filterVisibleWidgets($this->getWidgets());
    }

    /**
     * @return int | string | array<string, int | string | null>
     */
    public function getColumns(): int | string | array
    {
        return 1;
    }

    public static function canAccess(): bool
    {
        return \Gate::allows('viewAccountingDashboard', auth()->user());
    }
}
