<?php

namespace Tests\Feature;

use App\Filament\Pages\MyNotes;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class MyNotesTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_table_has_notes_column(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'notes'));
    }

    public function test_user_can_save_their_own_notes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(MyNotes::class)
            ->fillForm(['notes' => '# My private note'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('# My private note', $user->fresh()->notes);
    }

    public function test_editor_is_configured_tall(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        // Note: no HTTP GET assertion here — Filament's Authenticate
        // middleware 403s non-FilamentUser models when APP_ENV !== local,
        // and phpunit runs with APP_ENV=testing by design.

        Livewire::test(MyNotes::class)->assertHasNoFormErrors();

        $this->assertSame(
            '60vh',
            Livewire::test(MyNotes::class)->instance()->form->getComponent('data.notes')->getMinHeight()
        );
    }

    public function test_saving_notes_does_not_affect_other_users(): void
    {
        $user = User::factory()->create(['notes' => 'untouched']);
        $other = User::factory()->create();

        $this->actingAs($other);

        Livewire::test(MyNotes::class)
            ->fillForm(['notes' => 'other user note'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('other user note', $other->fresh()->notes);
        $this->assertSame('untouched', $user->fresh()->notes);
    }
}
