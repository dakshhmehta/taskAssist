@component('mail::message')
# New Leave Request

**{{ $leave->user->name }}** has submitted a leave request.

@component('mail::table')
| | |
| :--- | :--- |
| Type | {{ config('leave_types.' . $leave->code, $leave->code) }} |
| From | {{ $leave->from_date->format(config('app.date_format')) }} |
| To | {{ $leave->to_date->format(config('app.date_format')) }} |
| Days | {{ $leave->leave_days }}{{ $leave->half_day ? ' (Half Day)' : '' }} |
@endcomponent

**Reason:**

{!! nl2br(e($leave->remarks)) !!}

@component('mail::button', ['url' => url('/admin/user-leaves')])
Review Request
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
