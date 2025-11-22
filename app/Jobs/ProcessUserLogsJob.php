<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\UserCheckIn;
use App\Services\EmployeeTimeLogParser;
use App\UserTimeLogParser;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;

class ProcessUserLogsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $filePath, $period;

    public function __construct(string $filePath, $period)
    {
        $this->filePath = $filePath;
        $this->period = $period;
    }

    public function handle(): void
    {
        // Load Excel/CSV file into array
        $sheetData = Excel::toArray([], $this->filePath)[0]; // first sheet only

        $parser = new UserTimeLogParser();
        $parsed = $parser->parseSheet($sheetData);

        // dd($parsed);

        $period = Carbon::createFromFormat('Y-m', $this->period)->startOfMonth();

        foreach ($parsed as $employee) {
            $user = User::where('biometric_id', $employee['code'])->first();

            if (! $user) {
                continue;
            }

            foreach ($employee['logs'] as $day => $entries) {
                $period = $period->day($day);

                foreach ($entries as &$entry) {
                    if (isset($entry['in']) and ! empty($entry['in'])) {
                        [$hour, $minute] = explode(':', $entry['in']);

                        // Merge into existing Carbon date
                        $punchAt = $period->setTime($hour, $minute);

                        $checkin = new UserCheckIn([
                            'user_id' => $user->id,
                            'type' => 'IN',
                            'punch_at' => $punchAt,
                        ]);

                        $checkin->save();
                    }

                    if (isset($entry['out']) and ! empty($entry['out'])) {
                        [$hour, $minute] = explode(':', $entry['out']);

                        // Merge into existing Carbon date
                        $punchAt = $period->setTime($hour, $minute);

                        $checkin = new UserCheckIn([
                            'user_id' => $user->id,
                            'type' => 'OUT',
                            'punch_at' => $punchAt,
                        ]);

                        $checkin->save();
                    }
                }
            }
        }
    }
}
