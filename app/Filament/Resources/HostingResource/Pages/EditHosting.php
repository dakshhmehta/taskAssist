<?php

namespace App\Filament\Resources\HostingResource\Pages;

use App\Filament\Resources\HostingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditHosting extends EditRecord
{
    protected static string $resource = HostingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),

            Actions\Action::make('suspend')
                ->label('Suspend')
                ->color('danger')
                ->requiresConfirmation()
                ->action(fn () => $this->record->update(['suspended_at' => now()]))
                ->visible(fn () => !$this->record->suspended_at),

            Actions\Action::make('unsuspend')
                ->label('Unsuspend')
                ->color('success')
                ->requiresConfirmation()
                ->action(fn () => $this->record->update(['suspended_at' => null]))
                ->visible(fn () => $this->record->suspended_at),

            Actions\Action::make('ignore')
                ->label('Ignore')
                ->color('danger')
                ->requiresConfirmation()
                ->action(fn () => $this->record->ignore())
                ->visible(fn () => !$this->record->isIgnored()),

            Actions\Action::make('unignore')
                ->label('Unignore')
                ->color('warning')
                ->requiresConfirmation()
                ->action(fn () => $this->record->unIgnore())
                ->visible(fn () => $this->record->isIgnored()),
        ];
    }
}
