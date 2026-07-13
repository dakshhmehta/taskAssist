<?php

namespace App\Filament\Resources\DomainResource\Pages;

use App\Filament\Resources\DomainResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDomain extends EditRecord
{
    protected static string $resource = DomainResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\Action::make('ignore')
                ->label('Ignore')
                ->color('danger')
                ->requiresConfirmation()
                ->action(fn () => $this->record->ignore())
                ->visible(fn () => ! $this->record->isIgnored()),
            Actions\Action::make('unignore')
                ->label('Unignore')
                ->color('warning')
                ->requiresConfirmation()
                ->action(fn () => $this->record->unIgnore())
                ->visible(fn () => $this->record->isIgnored()),
        ];
    }
}
