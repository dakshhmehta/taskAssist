<?php

namespace App\Filament\Support;

use Filament\Forms\Components\TextInput;

class InvoiceTermInput
{
    public static function make(): TextInput
    {
        return TextInput::make('term')
            ->label('Advance by (years)')
            ->numeric()
            ->minValue(0)
            ->default(0)
            ->helperText('0 bills the current term. 1 moves the printed renewal to next year, and so on. Domain/hosting/email prices follow the shifted years.');
    }
}
