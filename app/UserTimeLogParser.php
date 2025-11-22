<?php

namespace App;

class UserTimeLogParser
{
    /**
     * Parse raw day log into IN/OUT records.
     */
    private function parseDayLog(string $rawLog): array
    {
        $times = array_filter(array_map('trim', explode("\n", $rawLog)));

        $records = [];
        $count = count($times);

        for ($i = 0; $i < $count; $i += 2) {
            $in  = $times[$i] ?? null;
            $out = $times[$i + 1] ?? null;

            if ($in) {
                $records[] = [
                    'in'  => $in,
                    'out' => $out
                ];
            }
        }

        return $records;
    }

    /**
     * Parse sheet data into structured employee logs.
     *
     * @param array $sheetData Full sheet data as array (rows from Excel/CSV).
     * @return array Parsed employee logs
     */
    public function parseSheet(array $sheetData): array
    {
        $parsed = [];

        // Row #1 → headers (dates start from col C)
        $headers = $sheetData[0]; 

        // Loop employee rows (from row #2 onwards, index 1)
        for ($i = 1; $i < count($sheetData); $i++) {
            $row = $sheetData[$i];

            if (empty($row[0]) && empty($row[1])) {
                continue; // skip blank rows
            }

            $employeeCode = $row[0]; // col A
            $employeeName = $row[1]; // col B

            $employeeLogs = [];

            for ($col = 2; $col < count($row); $col++) {
                $dayLog = trim((string) $row[$col]);

                if ($dayLog === '') {
                    continue;
                }

                // Day label comes from header row
                $date = $headers[$col] ?? "Day-$col";

                $employeeLogs[$date] = $this->parseDayLog($dayLog);
            }

            $parsed[] = [
                'code' => $employeeCode,
                'name' => $employeeName,
                'logs' => $employeeLogs
            ];
        }

        return $parsed;
    }
}
