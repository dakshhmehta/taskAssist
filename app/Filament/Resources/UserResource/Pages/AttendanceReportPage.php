<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\UserCheckIn;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Resources\Pages\ViewRecord;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AttendanceReportPage extends ViewRecord implements HasTable
{
    protected static string $resource = UserResource::class;

    use InteractsWithTable;

    protected static string $view = 'filament.resources.user-resource.pages.attendance-report-page';

    protected function authorizeAccess(): void
    {
        $user = Auth::user();

        if (!$user->is_admin && $user->id !== $this->record->id) {
            abort(Response::HTTP_FORBIDDEN);
        }
    }

    public function getHeading(): string | Htmlable
    {
        return $this->record->name . "'s Attendance Report";
    }

    public function getHeaderActions(): array
    {
        return [
            Action::make('check_in')
                ->label('Check In')
                ->form([
                    Select::make('type')
                        ->options([
                            'IN' => 'In',
                            'OUT' => 'Out',
                        ])
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $latitude = request()->input('latitude');
                    $longitude = request()->input('longitude');

                    UserCheckIn::create([
                        'user_id' => $this->record->id,
                        'type' => $data['type'],
                        'punch_at' => now(),
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                    ]);

                    Notification::make()
                        ->success()
                        ->title('Check-in recorded successfully')
                        ->send();
                })
                ->extraAttributes([
                    'x-data' => '{ 
                        officeLatitude: 23.2432096, 
                        officeLongitude: 69.6678079, 
                        maxDistance: 50,
                        init() {
                            this.checkLocation();
                        },
                        checkLocation() {
                            if (navigator.geolocation) {
                                navigator.geolocation.getCurrentPosition(
                                    (position) => {
                                        const distance = this.calculateDistance(
                                            position.coords.latitude,
                                            position.coords.longitude,
                                            this.officeLatitude,
                                            this.officeLongitude
                                        );
                                        
                                        if (distance > this.maxDistance) {
                                            this.$el.style.display = "none";
                                        } else {
                                            this.$el.querySelector("form").addEventListener("submit", (e) => {
                                                const form = e.target;
                                                const latInput = document.createElement("input");
                                                latInput.type = "hidden";
                                                latInput.name = "latitude";
                                                latInput.value = position.coords.latitude;
                                                form.appendChild(latInput);
                                                
                                                const lngInput = document.createElement("input");
                                                lngInput.type = "hidden";
                                                lngInput.name = "longitude";
                                                lngInput.value = position.coords.longitude;
                                                form.appendChild(lngInput);
                                            });
                                        }
                                    },
                                    () => {
                                        this.$el.style.display = "none";
                                    }
                                );
                            } else {
                                this.$el.style.display = "none";
                            }
                        },
                        calculateDistance(lat1, lon1, lat2, lon2) {
                            const R = 6371e3;
                            const φ1 = lat1 * Math.PI / 180;
                            const φ2 = lat2 * Math.PI / 180;
                            const Δφ = (lat2 - lat1) * Math.PI / 180;
                            const Δλ = (lon2 - lon1) * Math.PI / 180;
                            
                            const a = Math.sin(Δφ / 2) * Math.sin(Δφ / 2) +
                                    Math.cos(φ1) * Math.cos(φ2) *
                                    Math.sin(Δλ / 2) * Math.sin(Δλ / 2);
                            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
                            
                            return R * c;
                        }
                    }',
                ]),
        ];
    }

    public function table(Table $table): Table
    {
        $record = $this->record;

        return $table
            ->heading($record->name . "'s Attendance Report")
            ->query(
                fn() => UserCheckIn::select(
                    'user_checkins.id',
                    'user_checkins.user_id',
                    'user_checkins.punch_at as check_in',
                    \DB::raw('(SELECT b.punch_at 
                              FROM user_checkins b 
                              WHERE b.user_id = user_checkins.user_id 
                                AND b.type = "OUT" 
                                AND b.punch_at > user_checkins.punch_at 
                              ORDER BY b.punch_at ASC 
                              LIMIT 1) as check_out')
                )
                    ->where('type', 'IN')
                    ->where('user_id', $record->id)
                    ->orderBy('punch_at')
            )
            ->columns([
                TextColumn::make('check_in')->dateTime(),
                TextColumn::make('check_out')->dateTime(),
                TextColumn::make('duration')
                    ->getStateUsing(
                        fn($record) =>
                        $record->check_out
                            ? now()->parse($record->check_in)->diffAsCarbonInterval($record->check_out)->cascade()->forHumans()
                            : 'N/A'
                    ),
            ]);
    }
}
