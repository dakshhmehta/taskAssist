<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Jobs\ProcessUserLogsJob;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Action::make('upload_logs')
                ->label('Upload Logs')
                ->form([
                    // Period selector (Month + Year)
                    Select::make('period')
                        ->label('Period')
                        ->required()
                        ->options(function () {
                            // Generate last 12 months up to next month
                            return collect(range(0, 6))
                                ->mapWithKeys(function ($i) {
                                    $date = now()->subMonths($i);
                                    $value = $date->format('Y-m');   // 2025-09
                                    $label = $date->format('F Y');   // September 2025
                                    return [$value => $label];
                                });
                        })
                        ->default(now()->format('Y-m')),
                    FileUpload::make('file')
                        ->label('Excel/CSV File')
                        ->required()
                        ->disk('local')
                        ->directory('uploads/logs')
                        ->acceptedFileTypes([
                            'text/csv',
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ]),
                ])
                ->action(function (array $data) {
                    $path = storage_path("app/{$data['file']}");
                    ProcessUserLogsJob::dispatch($path, $data['period']);
                })
                ->color('success')
                ->icon('heroicon-o-arrow-up-tray')
        ];
    }
}
