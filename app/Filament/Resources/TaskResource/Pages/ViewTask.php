<?php

namespace App\Filament\Resources\TaskResource\Pages;

use App\Filament\Resources\TaskResource;
use Filament\Actions;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;

use Filament\Infolists\Infolist;

class ViewTask extends ViewRecord
{
    protected static string $resource = TaskResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                TextEntry::make('title'),
                TextEntry::make('description')
                    ->markdown(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('archive')
                ->label('Archive')
                ->icon('heroicon-o-archive-box')
                ->action(fn () => $this->record->ignore())
                ->visible(fn () => !$this->record->isIgnored())
                ->requiresConfirmation()
                ->color('warning'),

            Actions\Action::make('unarchive')
                ->label('Unarchive')
                ->icon('heroicon-o-archive-box-arrow-down')
                ->action(fn () => $this->record->unIgnore())
                ->visible(fn () => $this->record->isIgnored())
                ->requiresConfirmation()
                ->color('success'),
        ];
    }
}

