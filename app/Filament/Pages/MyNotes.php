<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class MyNotes extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-pencil-square';

    protected static string $view = 'filament.pages.my-notes';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'My Notes';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'notes' => auth()->user()->notes,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                MarkdownEditor::make('notes')
                    ->label('My Notes')
                    ->minHeight('60vh')
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();

        auth()->user()->update([
            'notes' => $state['notes'],
        ]);

        Notification::make()
            ->title('Notes saved')
            ->success()
            ->send();
    }
}
