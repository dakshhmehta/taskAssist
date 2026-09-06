@component('mail::message')
# Payment Request for {{ $period->format('F Y') }}

Please process the following team payments.

<table role="presentation" width="100%" cellpadding="10" cellspacing="0" border="1" style="width: 100%; border-collapse: collapse; margin: 16px 0;">
    <thead>
        <tr style="background-color: #f3f4f6;">
            <th align="right" style="border: 1px solid #d1d5db;">#</th>
            <th align="left" style="border: 1px solid #d1d5db;">Team Member</th>
            <th align="right" style="border: 1px solid #d1d5db;">Net Payable Amount</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($paymentRequests as $paymentRequest)
            <tr>
                <td align="right" style="border: 1px solid #d1d5db;">{{ $loop->iteration }}</td>
                <td style="border: 1px solid #d1d5db;">{{ $paymentRequest['user']->name }}</td>
                <td align="right" style="border: 1px solid #d1d5db;">{{ number_format($paymentRequest['net_payable_amount'], 2) }}</td>
            </tr>
        @empty
            <tr>
                <td align="right" style="border: 1px solid #d1d5db;">-</td>
                <td style="border: 1px solid #d1d5db;">No eligible team members</td>
                <td align="right" style="border: 1px solid #d1d5db;">0.00</td>
            </tr>
        @endforelse
        <tr style="background-color: #f3f4f6; font-weight: bold;">
            <td style="border: 1px solid #d1d5db;"></td>
            <td style="border: 1px solid #d1d5db;">Net Payable Total</td>
            <td align="right" style="border: 1px solid #d1d5db;">{{ number_format($paymentRequests->sum('net_payable_amount'), 2) }}</td>
        </tr>
    </tbody>
</table>

## Payment Details

<table role="presentation" width="100%" cellpadding="10" cellspacing="0" border="1" style="width: 100%; border-collapse: collapse; margin: 16px 0;">
    <thead>
        <tr style="background-color: #f3f4f6;">
            <th align="right" style="border: 1px solid #d1d5db;">#</th>
            <th align="left" style="border: 1px solid #d1d5db;">Team Member</th>
            <th align="right" style="border: 1px solid #d1d5db;">Tasks Completed</th>
            <th align="right" style="border: 1px solid #d1d5db;">Hours Worked</th>
            <th align="right" style="border: 1px solid #d1d5db;">Sick Leaves</th>
            <th align="right" style="border: 1px solid #d1d5db;">Payable Days / Total Working Days</th>
            <th align="right" style="border: 1px solid #d1d5db;">Net Payable Amount</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($paymentRequests as $paymentRequest)
            <tr>
                <td align="right" style="border: 1px solid #d1d5db;">{{ $loop->iteration }}</td>
                <td style="border: 1px solid #d1d5db;">{{ $paymentRequest['user']->name }}</td>
                <td align="right" style="border: 1px solid #d1d5db;">{{ $paymentRequest['completed_tasks'] }}</td>
                <td align="right" style="border: 1px solid #d1d5db;">{{ \App\Models\Timesheet::toHMS($paymentRequest['worked_minutes']) }}</td>
                <td align="right" style="border: 1px solid #d1d5db;">{{ $paymentRequest['sick_leave_days'] }}</td>
                <td align="right" style="border: 1px solid #d1d5db;">{{ $paymentRequest['payable_days'] === null ? 'N/A' : $paymentRequest['payable_days'] . ' / ' . $paymentRequest['working_days'] }}</td>
                <td align="right" style="border: 1px solid #d1d5db;">{{ number_format($paymentRequest['net_payable_amount'], 2) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

Thanks,<br>
{{ config('app.name') }}
@endcomponent
