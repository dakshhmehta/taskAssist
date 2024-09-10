<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Filament\Resources\UserResource\Widgets\SalaryDetails;
use App\Filament\Resources\UserResource\Widgets\TasksCount;
use App\Filament\Resources\UserResource\Widgets\UserTaskUtilization;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class ViewUser extends Page
{
    use InteractsWithRecord;
    use HasFiltersForm;

    
    protected static string $resource = UserResource::class;

    protected static string $view = 'filament.resources.user-resource.pages.view-user';

    public function mount(int | string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string|Htmlable
    {
        return $this->record->name.' Details';
    }

    public function filtersForm(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->schema([
                        DatePicker::make('startDate')
                            ->default(now()->subMonth()->startOfMonth()->format('Y-m-d')),
                        DatePicker::make('endDate')
                            ->default(now()->subMonth()->endOfMonth()->format('Y-m-d')),
                    ])
                    ->columns(3),
            ]);
    }

    public function getWidgets(): array
    {
        $data = ['user' => $this->record, 'filterData' => $this->filters];
        return [
            TasksCount::make($data),
            SalaryDetails::make($data),
            UserTaskUtilization::make($data),
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
