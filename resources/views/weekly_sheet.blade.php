<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Week Plan - {{ $startDate->format('d/m/Y') }} - {{ $endDate->format('d/m/Y') }}</title>
    <style type="text/css">
        table {
            border: 1px solid #000;
            width: 100%;
        }
        .center {
            text-align: center;
        }
        table tr td, table tr th {
            padding: 4px;
            border: 1px solid #000;
        }
    </style>
</head>
<body>
    <table>
        <thead>
            <tr>
                <td colspan="4" class="center"><h1>Tasks for {{ $startDate->format('d/m/Y') }} to {{ $endDate->format('d/m/Y') }}</h1></td>
            </tr>
            <tr>
                <th class="center">#</th>
                <th class="center" style="width: 150px;">Due Date</th>
                <th class="center" style="width: 150px;">Estimate</th>
                <th>Perticular</th>
            </tr>
        </thead>

        @foreach($tasks->groupBy('assignee_id') as &$userTasks)
            <tr>
                <th colspan="4">{{ $userTasks[0]->assignee->name }}</th>
            </tr>
            @foreach($userTasks as $i => $task)
                <tr>
                    <td class="center">{{ $i+1 }}</td>
                    <td class="center">{{ $task->due_date->format('d/m/Y') }}</td>
                    <td class="center">{{ $task->estimate_label }}</td>
                    <td>{{ $task->title }}</td>
                </tr>
            @endforeach
        @endforeach
    </table>
</body>
</html>