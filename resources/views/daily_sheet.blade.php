<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Standup - {{ $startDate->format('d/m/Y') }}</title>
    <style type="text/css">
        table {
            /* border: 1px solid #000; */
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
@php
$lines = 2
@endphp
    <table>
        <tr>
            <td width="60%">
                <table>
                    <tr>
                        <td colspan="4" class="center"><h1>{{ $startDate->format('d/m/Y') }}</h1></td>
                    </tr>
                    <tr>
                        <th class="center">#</th>
                        <!-- <th class="center" style="width: 150px;">Estimate</th> -->
                        <th>Assignee</th>
                    </tr>

                    @foreach($tasks->groupBy('assignee_id') as &$userTasks)
                        @php $lines++ @endphp
                        <tr>
                            <td width="100" class="center">
                                P&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                L&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                                A
                            </td>
                            <th>{{ $userTasks[0]->assignee->name }}</th>
                        </tr>
                        @foreach($userTasks as $i => $task)
                            @php $lines++ @endphp
                                <tr>
                                <td class="center">{{ $i+1 }}</td>
                                <!-- <td class="center">{{ $task->estimate_label }}</td> -->
                                <td>{{ $task->title }}</td>
                            </tr>
                        @endforeach
                    @endforeach
                </table>
            </td>
            <td>
                <table>
                    <tr>
                        <th class="center" colspan="2"><h1>Tasks</h1></th>
                    </tr>
                    @while($lines > 0)
                        <tr>
                            <td width="50"></td>
                            <td>&nbsp;</td>
                        </tr>
                        @php $lines-- @endphp
                    @endwhile
                </table>
            </td>
        </tr>
    </table>
</body>
</html>