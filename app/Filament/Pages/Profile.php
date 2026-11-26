<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\FileUpload;
use Filament\Pages\Auth\EditProfile;

class Profile extends EditProfile
{
    /**
     * @return array<int | string, string | \Filament\Forms\Form>
     */
    protected function getForms(): array
    {
        return [
            'form' => $this->form(
                $this->makeForm()
                    ->schema([
                        FileUpload::make('avatar_path')
                            ->label('Profile photo')
                            ->avatar()
                            ->image()
                            ->imageEditor()
                            ->maxSize(2048),
                        $this->getNameFormComponent(),
                        $this->getEmailFormComponent(),
                        $this->getPasswordFormComponent(),
                        $this->getPasswordConfirmationFormComponent(),
                    ])
                    ->operation('edit')
                    ->model($this->getUser())
                    ->statePath('data')
                    ->inlineLabel(! static::isSimple()),
            ),
        ];
    }
}
