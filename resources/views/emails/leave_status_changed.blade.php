@component('mail::message')
# Leave Request {{ ucfirst(strtolower($leave->status)) }}

Hi {{ $leave->user->name }},

Your leave request has been **{{ strtolower($leave->status) }}**.

@component('mail::table')
| | |
| :--- | :--- |
| Type | {{ config('leave_types.' . $leave->code, $leave->code) }} |
| From | {{ $leave->from_date->format(config('app.date_format')) }} |
| To | {{ $leave->to_date->format(config('app.date_format')) }} |
| Days | {{ $leave->leave_days }}{{ $leave->half_day ? ' (Half Day)' : '' }} |
@endcomponent

@if($leave->admin_remarks)
**Remarks:**

{!! nl2br(e($leave->admin_remarks)) !!}
@endif

Thanks,<br>
{{ config('app.name') }}
@endcomponent
