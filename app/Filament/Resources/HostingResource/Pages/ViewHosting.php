<?php

namespace App\Filament\Resources\HostingResource\Pages;

use App\Filament\Resources\HostingResource;
use Filament\Actions;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewHosting extends ViewRecord
{
    protected static string $resource = HostingResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Hosting Details')
                    ->schema([
                        TextEntry::make('domain'),
                        TextEntry::make('server')
                            ->placeholder('-'),
                        TextEntry::make('package.storage')
                            ->label('Package')
                            ->placeholder('-'),
                        TextEntry::make('client.billing_name')
                            ->label('Client')
                            ->placeholder('-'),
                        TextEntry::make('expiry_date')
                            ->label('Expiry')
                            ->date('d-m-Y')
                            ->placeholder('-'),
                        TextEntry::make('suspended_at')
                            ->label('Suspended At')
                            ->dateTime('d-m-Y H:i')
                            ->placeholder('-'),
                        TextEntry::make('terminated_at')
                            ->label('Terminated At')
                            ->dateTime('d-m-Y H:i')
                            ->placeholder('-'),
                    ])
                    ->columns(2),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
