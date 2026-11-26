<?php

namespace Tests\Feature;

use App\Filament\Pages\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ProfilePhotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_table_has_avatar_path_column(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'avatar_path'));
    }

    public function test_profile_page_shows_the_photo_upload(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(Profile::class)
            ->assertFormFieldExists('avatar_path');
    }

    public function test_profile_photo_upload_is_served_on_the_profile_route(): void
    {
        config(['app.env' => 'local']);

        $user = User::factory()->create(['id' => 1]);

        $this->actingAs($user)
            ->get('/admin/profile')
            ->assertOk()
            ->assertSee('Profile photo');
    }

    public function test_user_can_upload_and_remove_a_profile_photo(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $this->actingAs($user);

        Livewire::test(Profile::class)
            ->fillForm([
                'avatar_path' => UploadedFile::fake()->image('me.jpg'),
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();

        $this->assertNotNull($user->avatar_path);
        Storage::disk('public')->assertExists($user->avatar_path);

        Livewire::test(Profile::class)
            ->fillForm(['avatar_path' => null])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull($user->fresh()->avatar_path);
    }
}
