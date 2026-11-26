<x-filament-panels::page>
    @php($report = $this->report)

    <div class="flex items-center gap-3">
        <label for="tracker-month" class="text-sm font-medium text-gray-950 dark:text-white">Month</label>
        <x-filament::input.wrapper>
            <x-filament::input id="tracker-month" type="month" wire:model.live="month" />
        </x-filament::input.wrapper>

        <label class="flex items-center gap-3 text-sm font-medium text-gray-950 dark:text-white">
            <x-filament::input.checkbox wire:model.live="includeOwner" />
            Include owner hours
        </label>
    </div>

    <x-filament-widgets::widgets
        :columns="$this->getColumns()"
        :data="$this->getWidgetData()"
        :widgets="$this->getVisibleWidgets()"
    />

    <x-filament::section heading="Hours per project">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs font-medium text-gray-500 dark:text-gray-400">
                    <th class="w-8 py-2">#</th>
                    <th class="py-2">Project</th>
                    <th class="py-2 text-right">Tasks</th>
                    <th class="py-2 text-right">People</th>
                    <th class="py-2 text-right">Hours</th>
                    <th class="py-2 text-right">Share</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($report['projects'] as $project)
                    <tr class="align-top border-t border-gray-100 dark:border-gray-700">
                        <td class="py-2 px-3 text-gray-500 dark:text-gray-400">{{ $loop->iteration }}</td>
                        <td class="py-2 px-3">
                            <details>
                                <summary class="cursor-pointer font-medium text-gray-950 dark:text-white">
                                    {{ $project['name'] }}
                                </summary>

                                <div class="mt-2 rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-800">
                                    <table class="w-full text-xs">
                                        <thead>
                                            <tr class="text-left font-medium text-gray-500 dark:text-gray-400">
                                                <th class="w-6 py-1 px-2">#</th>
                                                <th class="py-1 px-2">Member</th>
                                                <th class="py-1 px-2 text-right">Hours</th>
                                                <th class="py-1 px-2 text-right">Share</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($project['members'] as $member)
                                                <tr class="border-t border-gray-200 dark:border-gray-700">
                                                    <td class="py-1 px-2 text-gray-500 dark:text-gray-400">{{ $loop->iteration }}</td>
                                                    <td class="py-1 px-2 text-gray-950 dark:text-white">{{ $member['name'] }}</td>
                                                    <td class="py-1 px-2 text-right">{{ number_format($member['hours'], 1) }}h</td>
                                                    <td class="py-1 px-2 text-right">{{ number_format($member['share'], 1) }}%</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                        <tfoot>
                                            <tr class="border-t border-gray-200 font-semibold text-gray-950 dark:border-gray-700 dark:text-white">
                                                <td class="pt-2 px-2"></td>
                                                <td class="pt-2 px-2">Total</td>
                                                <td class="pt-2 px-2 text-right">{{ number_format($project['hours'], 1) }}h</td>
                                                <td class="pt-2 px-2 text-right">100%</td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </details>
                        </td>
                        <td class="py-2 px-3 text-right">{{ $project['tasks'] }}</td>
                        <td class="py-2 px-3 text-right">{{ $project['people'] }}</td>
                        <td class="py-2 px-3 text-right">{{ number_format($project['hours'], 1) }}h</td>
                        <td class="py-2 px-3 text-right">{{ number_format($project['share'], 1) }}%</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="py-3 text-center text-gray-500 dark:text-gray-400">
                            No completed timesheet entries in this month.
                        </td>
                    </tr>
                @endforelse
            </tbody>
            @if ($report['projects'])
                <tfoot>
                    <tr class="border-t border-gray-200 font-semibold text-gray-950 dark:border-gray-700 dark:text-white">
                        <td class="pt-2 px-3"></td>
                        <td class="pt-2 px-3">{{ $report['totals']['projects'] }} projects</td>
                        <td class="pt-2 px-3 text-right">{{ $report['totals']['tasks'] }}</td>
                        <td class="pt-2 px-3 text-right">{{ $report['totals']['members'] }}</td>
                        <td class="pt-2 px-3 text-right">{{ number_format($report['totals']['hours'], 1) }}h</td>
                        <td class="pt-2 px-3 text-right">100%</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </x-filament::section>

    <x-filament::section heading="Member roll-up">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs font-medium text-gray-500 dark:text-gray-400">
                    <th class="w-8 py-2">#</th>
                    <th class="py-2">Member</th>
                    <th class="py-2 text-right">Projects</th>
                    <th class="py-2 text-right">Hours</th>
                    <th class="py-2 text-right">Share</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($report['members'] as $member)
                    <tr class="border-t border-gray-100 dark:border-gray-700">
                        <td class="py-2 px-3 text-gray-500 dark:text-gray-400">{{ $loop->iteration }}</td>
                        <td class="py-2 px-3 font-medium text-gray-950 dark:text-white">{{ $member['name'] }}</td>
                        <td class="py-2 px-3 text-right">{{ $member['projects'] }}</td>
                        <td class="py-2 px-3 text-right">{{ number_format($member['hours'], 1) }}h</td>
                        <td class="py-2 px-3 text-right">{{ number_format($member['share'], 1) }}%</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="py-3 text-center text-gray-500 dark:text-gray-400">No entries.</td>
                    </tr>
                @endforelse
            </tbody>
            @if ($report['members'])
                <tfoot>
                    <tr class="border-t border-gray-200 font-semibold text-gray-950 dark:border-gray-700 dark:text-white">
                        <td class="pt-2 px-3"></td>
                        <td class="pt-2 px-3">{{ $report['totals']['members'] }} members</td>
                        <td class="pt-2 px-3 text-right">{{ $report['totals']['projects'] }}</td>
                        <td class="pt-2 px-3 text-right">{{ number_format($report['totals']['hours'], 1) }}h</td>
                        <td class="pt-2 px-3 text-right">100%</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </x-filament::section>

    <x-filament::section :heading="$report['excluded']['included'] ? 'Owner hours (included)' : 'Owner hours excluded'">
        <p class="text-2xl font-semibold text-gray-950 dark:text-white">
            {{ number_format($report['excluded']['hours'], 1) }}h
        </p>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            {{ $report['excluded']['entries'] }} {{ \Illuminate\Support\Str::plural('entry', $report['excluded']['entries']) }}
            {{ $report['excluded']['included'] ? 'included in the team totals above.' : 'not counted in the team totals above.' }}
        </p>
    </x-filament::section>
</x-filament-panels::page>
