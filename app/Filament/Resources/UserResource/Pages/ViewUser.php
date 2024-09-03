<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Filament\Resources\UserResource\Widgets\TasksAssignedCount;
use App\Models\User;
use Filament\Resources\Pages\Page;
use Filament\Tables\Actions\Concerns\CanAccessSelectedRecords;

class ViewUser extends Page
{
    protected static string $resource = UserResource::class;

    protected static string $view = 'filament.resources.user-resource.pages.view-user';

    public User $user;

    public function mount(User $record): void
    {
        $this->user = $record;
    }

    public function getWidgets(): array
    {
        return [
           TasksAssignedCount::make(['user' => $this->user]),
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
        return 12;
    }
}
