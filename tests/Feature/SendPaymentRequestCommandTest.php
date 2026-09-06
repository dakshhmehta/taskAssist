<?php

namespace Tests\Feature;

use App\Mail\PaymentRequestMail;
use App\Models\Task;
use App\Models\Timesheet;
use App\Models\User;
use App\Models\UserLeave;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendPaymentRequestCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_previous_month_payment_details_to_admins(): void
    {
        Mail::fake();
        Carbon::setTestNow('2026-04-15 10:00:00');

        try {
            $admin = User::factory()->create(['email' => 'admin@example.com']);
            $employee = User::factory()->create([
                'name' => 'Jane Employee',
                'salary' => 26000,
                'salary_type' => 'monthly',
            ]);
            $disabledEmployee = User::factory()->create([
                'salary' => 26000,
                'is_disabled' => true,
            ]);

            $completedTask = Task::create([
                'assignee_id' => $employee->id,
                'title' => 'March task',
                'completed_at' => '2026-03-31 23:59:59',
            ]);

            Task::create([
                'assignee_id' => $employee->id,
                'title' => 'April task',
                'completed_at' => '2026-04-01 00:00:00',
            ]);

            Timesheet::create([
                'user_id' => $employee->id,
                'task_id' => $completedTask->id,
                'start_at' => '2026-03-31 21:30:00',
                'end_at' => '2026-03-31 23:30:00',
            ]);

            UserLeave::withoutEvents(function () use ($employee): void {
                UserLeave::create([
                    'user_id' => $employee->id,
                    'from_date' => '2026-03-10',
                    'to_date' => '2026-03-11',
                    'code' => 'SL',
                    'status' => 'APPROVED',
                ]);

                UserLeave::create([
                    'user_id' => $employee->id,
                    'from_date' => '2026-03-12',
                    'to_date' => '2026-03-12',
                    'code' => 'SL',
                    'half_day' => true,
                    'status' => 'APPROVED',
                ]);
            });

            $this->artisan('payments:send-request')
                ->expectsOutput('Sent payment request for March 2026 to admin@example.com')
                ->expectsOutput('Payment request entries: 1')
                ->assertSuccessful();

            Mail::assertSent(PaymentRequestMail::class);

            $mail = Mail::sent(PaymentRequestMail::class)->first();
            $request = $mail->paymentRequests->firstWhere('user.id', $employee->id);

            $this->assertTrue($mail->hasTo($admin->email));
            $this->assertSame('2026-03', $mail->period->format('Y-m'));
            $this->assertCount(1, $mail->paymentRequests);
            $this->assertFalse($mail->paymentRequests->contains('user.id', $disabledEmployee->id));
            $this->assertSame(1, $request['completed_tasks']);
            $this->assertSame(120, $request['worked_minutes']);
            $this->assertSame(2.5, $request['sick_leave_days']);
            $this->assertSame(23.5, $request['payable_days']);
            $this->assertSame(26.0, $request['working_days']);
            $this->assertSame(23500.0, $request['net_payable_amount']);

            $rendered = $mail->render();

            $this->assertStringContainsString('Jane Employee', $rendered);
            $this->assertStringContainsString('Net Payable Total', $rendered);
            $this->assertStringContainsString('border-collapse: collapse', $rendered);
            $this->assertStringContainsString('Tasks Completed', $rendered);
            $this->assertStringContainsString('02:00', $rendered);
            $this->assertStringContainsString('23.5 / 26', $rendered);
            $this->assertStringContainsString('23,500.00', $rendered);
        } finally {
            Carbon::setTestNow();
        }
    }
}
