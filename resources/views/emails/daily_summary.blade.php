@component('mail::message')
# Hello {{ $user->name }},

Here is your task summary for today ({{ $date->format('d M, Y') }}):

---

## ✅ Tasks Created Today

@if($createdTasks->count())
<table style="width: 100%; border-collapse: collapse;">
    <thead>
        <tr>
            <th style="border-bottom: 1px solid #ccc; text-align: left;">#</th>
            <th style="border-bottom: 1px solid #ccc; text-align: left;">Title</th>
            <th style="border-bottom: 1px solid #ccc;">Estimate</th>
            <th style="border-bottom: 1px solid #ccc;">Due Date</th>
        </tr>
    </thead>
    <tbody>
        @foreach($createdTasks as $i => $task)
        <tr>
            <td style="text-align: center; padding: 6px 0;">{{ $i+1 }}</td>
            <td style="padding: 6px 0;">{{ $task->display_title }}</td>
            <td style="text-align: center;">{{ $task->estimate_label ?? 'N/A' }}</td>
            <td style="text-align: center;">{{ $task->due_date?->format(config('app.date_format')) ?? 'N/A' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@else
<p>No tasks were created today.</p>
@endif

---

## 🎯 Tasks Completed Today

@if($completedTasks->count())
<table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
    <thead>
        <tr>
            <th style="border-bottom: 1px solid #ccc;">#</th>
            <th style="border-bottom: 1px solid #ccc; text-align: left;">Title</th>
            <th style="border-bottom: 1px solid #ccc;">Estimate</th>
            <!-- <th style="border-bottom: 1px solid #ccc;">Created On</th> -->
            <th style="border-bottom: 1px solid #ccc;">Time Taken</th>
            <th style="border-bottom: 1px solid #ccc;">Days Taken</th>
        </tr>
    </thead>
    <tbody>
        @foreach($completedTasks as $j => $task)
        <tr>
            <td style="text-align: center; padding: 6px 0;">{{ $j+1 }}</td>
            <td style="padding: 6px 0;">{{ $task->display_title }}<br />
                <i>{!! $task->lastComment()?->comment ?? 'No comment' !!}</i>
            </td>
            <td style="text-align: center;">{{ $task->estimate_label ?? 'N/A' }}</td>
            <!-- <td style="text-align: center;">{{ $task->created_at->format(config('app.date_format')) }}</td> -->
            <td style="text-align: center;">{{ $task->hms }}</td>
            <td style="text-align: center;">{{ $task->created_at->diffInDays($task->completed_at) }} day(s)</td>
        </tr>
        @endforeach
    </tbody>
</table>
@else
<p>No tasks were completed today.</p>
@endif

---

Thanks,
{{ config('app.name') }}
@endcomponent