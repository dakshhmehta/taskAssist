<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserAvatarTest extends TestCase
{
    use RefreshDatabase;

    public function test_avatar_url_is_null_without_a_photo(): void
    {
        $user = User::factory()->create();

        $this->assertNull($user->getFilamentAvatarUrl());
    }

    public function test_avatar_url_points_at_the_uploaded_photo(): void
    {
        config(['filesystems.disks.public.url' => 'http://localhost/storage']);

        $user = User::factory()->create(['avatar_path' => 'avatars/me.jpg']);

        $this->assertSame('http://localhost/storage/avatars/me.jpg', $user->getFilamentAvatarUrl());
    }

    public function test_uploading_a_photo_stores_a_grayscale_copy(): void
    {
        config(['filesystems.disks.public.url' => 'http://localhost/storage']);
        Storage::fake('public');

        $user = User::factory()->create();

        $this->storeFakeImage('avatars/me.jpg');

        $user->update(['avatar_path' => 'avatars/me.jpg']);

        Storage::disk('public')->assertExists('avatars/grey/me.jpg');
        $this->assertStringContainsString(
            'avatars/grey/me.jpg',
            $user->fresh()->getGrayscaleAvatarUrl()
        );
    }

    public function test_removing_the_photo_deletes_the_grayscale_copy(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $this->storeFakeImage('avatars/me.jpg');

        $user->update(['avatar_path' => 'avatars/me.jpg']);
        Storage::disk('public')->assertExists('avatars/grey/me.jpg');

        $user->update(['avatar_path' => null]);

        Storage::disk('public')->assertMissing('avatars/grey/me.jpg');
        $this->assertNull($user->fresh()->getGrayscaleAvatarUrl());
    }

    private function storeFakeImage(string $path): void
    {
        $file = UploadedFile::fake()->image('me.jpg', 40, 40);

        Storage::disk('public')->put($path, file_get_contents($file->getRealPath()));
    }
}
