<?php

namespace App\Console\Commands;

use App\Mail\PaymentRequestMail;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendPaymentRequestCommand extends Command
{
    protected $signature = 'payments:send-request';

    protected $description = 'Send the previous month payment request to administrators';

    public function handle(): int
    {
        $period = now()->subMonthNoOverflow()->startOfMonth();
        $periodEnd = $period->copy()->endOfMonth();
        $admins = User::all()->filter(fn (User $user) => $user->is_admin);

        if ($admins->isEmpty()) {
            $this->error('No administrators found to receive the payment request.');

            return self::FAILURE;
        }

        $paymentRequests = User::query()
            ->where('is_disabled', false)
            ->whereNotNull('salary')
            ->get()
            ->map(fn (User $user) => [
                'user' => $user,
                ...$user->paymentDetailsForPeriod($period, $periodEnd),
            ]);

        $recipientEmails = $admins->pluck('email')->all();

        Mail::to($recipientEmails)->send(new PaymentRequestMail($period, $paymentRequests));

        $this->info('Sent payment request for '.$period->format('F Y').' to '.implode(', ', $recipientEmails));
        $this->info('Payment request entries: '.$paymentRequests->count());

        return self::SUCCESS;
    }
}
