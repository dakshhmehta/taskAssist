<?php

namespace Tests\Feature;

use App\Filament\Resources\UserLeaveResource\Pages\CreateUserLeave;
use App\Models\User;
use App\Models\UserLeave;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class AdminCreateLeaveTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['id' => 1]);
    }

    public function test_admin_can_create_a_leave_request_for_a_team_member(): void
    {
        $admin = $this->admin();
        $employee = User::factory()->create(['id' => 2]);

        $this->actingAs($admin);

        Livewire::test(CreateUserLeave::class)
            ->fillForm([
                'user_id' => $employee->id,
                'from_date' => now()->addDays(10)->toDateString(),
                'to_date' => now()->addDays(11)->toDateString(),
                'code' => 'SL',
                'remarks' => 'Family function',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(1, UserLeave::count());
    }

    public function test_admin_can_create_a_leave_request_for_themselves_without_touching_the_user_select(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin);

        Livewire::test(CreateUserLeave::class)
            ->fillForm([
                'from_date' => now()->addDays(10)->toDateString(),
                'to_date' => now()->addDays(10)->toDateString(),
                'code' => 'SL',
                'remarks' => 'Personal work',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame($admin->id, UserLeave::first()->user_id);
    }

    public function test_admin_can_create_a_cl_leave_more_than_four_days_ahead(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin);

        Livewire::test(CreateUserLeave::class)
            ->fillForm([
                'from_date' => now()->addDays(6)->toDateString(),
                'to_date' => now()->addDays(7)->toDateString(),
                'code' => 'CL',
                'remarks' => 'Wedding',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('CL', UserLeave::first()->code);
    }

    public function test_admin_sees_a_validation_error_for_a_short_notice_cl(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin);

        Livewire::test(CreateUserLeave::class)
            ->fillForm([
                'from_date' => now()->addDay()->toDateString(),
                'to_date' => now()->addDays(2)->toDateString(),
                'code' => 'CL',
                'remarks' => 'Too soon',
            ])
            ->call('create')
            ->assertHasFormErrors(['from_date' => 'after_or_equal'])
            ->assertSee('CL must be applied at least 4 days in advance');

        $this->assertSame(0, UserLeave::count());
    }

    public function test_model_still_rejects_a_short_notice_cl_created_outside_the_form(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin);

        $this->expectException(ValidationException::class);

        UserLeave::create([
            'user_id' => $admin->id,
            'from_date' => now()->addDay()->toDateString(),
            'to_date' => now()->addDays(2)->toDateString(),
            'code' => 'CL',
            'remarks' => 'Added through a non-form path',
        ]);
    }

    public function test_admin_can_create_a_half_day_leave(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin);

        Livewire::test(CreateUserLeave::class)
            ->fillForm([
                'from_date' => now()->addDays(10)->toDateString(),
                'to_date' => now()->addDays(10)->toDateString(),
                'half_day' => true,
                'code' => 'SL',
                'remarks' => 'Doctor visit',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertTrue((bool) UserLeave::first()->half_day);
    }
}
