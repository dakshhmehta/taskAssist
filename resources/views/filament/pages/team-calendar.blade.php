<x-filament-panels::page>
    @php($calendar = $this->calendar)

    <div class="flex items-center gap-3">
        <label for="calendar-month" class="text-sm font-medium text-gray-950 dark:text-white">Month</label>
        <x-filament::input.wrapper>
            <x-filament::input id="calendar-month" type="month" wire:model.live="month" />
        </x-filament::input.wrapper>
    </div>

    <div class="flex items-center gap-4 text-xs text-gray-500 dark:text-gray-400">
        @foreach ([
            'Sunday' => '#fecaca',
            'Holiday' => '#fecaca',
            'All present' => '#bbf7d0',
            'Absent' => '#fde68a',
        ] as $label => $colour)
            <span class="flex items-center gap-2">
                <span style="display:inline-block;height:0.75rem;width:0.75rem;border-radius:0.25rem;border:1px solid rgba(0,0,0,0.1);background:{{ $colour }}"></span>
                {{ $label }}
            </span>
        @endforeach
    </div>

    <x-filament::section
        :heading="$calendar['label']"
        :description="$calendar['absent_days'].' days with absences this month'"
    >
        <div style="display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:0.5rem">
            @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $weekday)
                <div class="text-xs font-medium text-gray-500 dark:text-gray-400" style="padding:0.25rem 0.5rem">
                    {{ $weekday }}
                </div>
            @endforeach

            @foreach ($calendar['weeks'] as $week)
                @foreach ($week as $day)
                    @if ($day === null)
                        <div></div>
                    @else
                        <div style="display:flex;flex-direction:column;min-height:6.5rem;border-radius:0.5rem;border:1px solid rgba(0,0,0,0.08);padding:0.5rem;background:{{ match ($day['status']) {
                            'holiday', 'sunday' => '#fecaca',
                            'leave' => '#fde68a',
                            default => '#bbf7d0',
                        } }};color:#111827">
                            <div class="flex items-start justify-between gap-2">
                                <span class="text-sm font-semibold">{{ $day['day'] }}</span>

                                @if ($day['holiday'])
                                    <span class="text-xs font-medium" style="color:#991b1b;text-align:right">
                                        {{ $day['holiday'] }}
                                    </span>
                                @endif
                            </div>

                            <div class="mt-2" style="display:flex;flex-wrap:wrap;gap:0.25rem">
                                @foreach ($day['absent'] as $member)
                                    @if ($member['photo'])
                                        <img
                                            src="{{ $member['photo'] }}"
                                            alt="{{ $member['name'] }}"
                                            title="{{ $member['name'] }} ({{ $member['code'] }})"
                                            style="height:1.75rem;width:1.75rem;border-radius:9999px;object-fit:cover;filter:grayscale(1)"
                                        />
                                    @else
                                        <span
                                            title="{{ $member['name'] }} ({{ $member['code'] }})"
                                            style="display:inline-flex;height:1.75rem;width:1.75rem;align-items:center;justify-content:center;border-radius:9999px;background:#d1d5db;color:#1f2937;font-size:0.7rem;font-weight:600"
                                        >
                                            {{ \Illuminate\Support\Str::of($member['name'])->substr(0, 1)->upper() }}
                                        </span>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
            @endforeach
        </div>
    </x-filament::section>
</x-filament-panels::page>
