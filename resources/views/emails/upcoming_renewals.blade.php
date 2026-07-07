@component('mail::message')
# Upcoming Renewals Check ({{ $windowDays }}-day window)

**{{ $referenceDate->format('l, d-m-Y') }}**

---

## EXPIRED ({{ $expiredRenewals->count() }})

@forelse($expiredRenewals as $renewal)
- {{ $renewal['domain'] }} - {{ $renewal['type'] }}@if($renewal['type'] === 'GSuite' && $renewal['accounts']) [{{ $renewal['accounts'] }} accounts]@endif - {{ $renewal['days_overdue'] }} day{{ $renewal['days_overdue'] === 1 ? '' : 's' }} overdue
@empty
- No expired renewals.
@endforelse

---

## UPCOMING ({{ $upcomingRenewals->count() }})

@forelse($upcomingRenewals as $renewal)
- {{ $renewal['domain'] }} - {{ $renewal['type'] }}@if($renewal['type'] === 'GSuite' && $renewal['accounts']) [{{ $renewal['accounts'] }} accounts]@endif - {{ \Carbon\Carbon::parse($renewal['expiry_date'])->format('d-m-Y') }} ({{ $renewal['days_until_expiry'] }} days)
@empty
- No upcoming renewals in the next {{ $windowDays }} days.
@endforelse

---

## PRIORITY ACTIONS

@if($expiredRenewals->count())
- Renew {{ $expiredRenewals->count() }} expired item{{ $expiredRenewals->count() === 1 ? '' : 's' }} immediately.
@else
- No expired items need immediate action.
@endif

Thanks,<br>
{{ config('app.name') }}
@endcomponent
