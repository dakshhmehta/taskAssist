<?php

namespace Tests\Feature;

use App\Mail\LeaveRequestSubmittedMail;
use App\Mail\LeaveStatusChangedMail;
use App\Models\User;
use App\Models\UserLeave;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LeaveNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Mail::fake();
    }

    private function admin(): User
    {
        // The first user is treated as the admin (User::is_admin === id 1).
        return User::factory()->create(['email' => 'admin@example.com']);
    }

    private function createLeave(User $employee, string $status = 'NEW', array $overrides = []): UserLeave
    {
        return UserLeave::create(array_merge([
            'user_id' => $employee->id,
            'from_date' => now()->addDays(10)->toDateString(),
            'to_date' => now()->addDays(11)->toDateString(),
            'code' => 'SL',
            'remarks' => 'Not feeling well',
            'status' => $status,
        ], $overrides));
    }

    public function test_admins_are_notified_when_a_leave_request_is_made(): void
    {
        $admin = $this->admin();
        $employee = User::factory()->create(['email' => 'employee@example.com']);

        $leave = $this->createLeave($employee);

        Mail::assertSent(LeaveRequestSubmittedMail::class, function (LeaveRequestSubmittedMail $mail) use ($admin, $leave) {
            return $mail->hasTo($admin->email)
                && $mail->leave->id === $leave->id
                && str_contains($mail->render(), 'Not feeling well');
        });
    }

    public function test_admins_are_not_notified_for_an_already_approved_leave(): void
    {
        $this->admin();
        $employee = User::factory()->create(['email' => 'employee@example.com']);

        $this->createLeave($employee, 'APPROVED');

        Mail::assertNotSent(LeaveRequestSubmittedMail::class);
    }

    public function test_no_notifications_are_sent_when_the_admin_raises_their_own_leave(): void
    {
        $admin = $this->admin();

        $leave = $this->createLeave($admin);

        Mail::assertNotSent(LeaveRequestSubmittedMail::class);

        $this->actingAs($admin);
        $leave->approve();

        Mail::assertNotSent(LeaveStatusChangedMail::class);
    }

    public function test_no_status_notification_is_sent_when_the_admin_own_leave_is_rejected(): void
    {
        $admin = $this->admin();

        $leave = $this->createLeave($admin);

        $this->actingAs($admin);
        $leave->reject('Not applicable');

        Mail::assertNotSent(LeaveStatusChangedMail::class);
    }

    public function test_team_member_is_notified_when_the_request_is_approved(): void
    {
        $admin = $this->admin();
        $employee = User::factory()->create(['email' => 'employee@example.com']);

        $leave = $this->createLeave($employee);

        $this->actingAs($admin);
        $leave->approve();

        Mail::assertSent(LeaveStatusChangedMail::class, function (LeaveStatusChangedMail $mail) use ($employee, $leave) {
            return $mail->hasTo($employee->email)
                && $mail->leave->id === $leave->id
                && $mail->leave->status === 'APPROVED';
        });
    }

    public function test_team_member_is_notified_with_remarks_when_the_request_is_rejected(): void
    {
        $admin = $this->admin();
        $employee = User::factory()->create(['email' => 'employee@example.com']);

        $leave = $this->createLeave($employee);

        $this->actingAs($admin);
        $leave->reject('Insufficient leave balance');

        Mail::assertSent(LeaveStatusChangedMail::class, function (LeaveStatusChangedMail $mail) use ($employee) {
            return $mail->hasTo($employee->email)
                && $mail->leave->status === 'REJECTED'
                && $mail->leave->admin_remarks === 'Insufficient leave balance'
                && str_contains($mail->render(), 'Insufficient leave balance');
        });
    }
}
