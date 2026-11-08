<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Identity\Models\User;
use App\Modules\Listings\Models\Media;
use Database\Seeders\AdminSeeder;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OpsTest extends TestCase
{
    use RefreshDatabase;

    public function test_media_gc_removes_only_old_orphans(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();

        $make = function (string $path, int $ageHours) use ($owner): Media {
            Storage::disk('public')->put($path, 'x');
            $m = Media::query()->create([
                'owner_id' => $owner->id, 'disk' => 'public', 'original_path' => $path,
                'mime' => 'image/jpeg', 'bytes' => 1, 'width' => 10, 'height' => 10,
                'sha256' => hash('sha256', $path), 'status' => Media::STATUS_READY,
            ]);
            Media::query()->whereKey($m->id)->update(['created_at' => now()->subHours($ageHours)]);

            return $m;
        };

        $old = $make('media/a.jpg', 48);
        $fresh = $make('media/b.jpg', 1);

        $this->artisan('media:gc', ['--dry-run' => true])->assertSuccessful();
        $this->assertDatabaseHas('media', ['id' => $old->id]);

        $this->artisan('media:gc')->assertSuccessful();

        $this->assertDatabaseMissing('media', ['id' => $old->id]);
        $this->assertDatabaseHas('media', ['id' => $fresh->id]);
        Storage::disk('public')->assertMissing('media/a.jpg');
        Storage::disk('public')->assertExists('media/b.jpg');
    }

    public function test_admin_seeder_is_noop_without_env_and_creates_verified_admin_with_env(): void
    {
        $this->seed(AdminSeeder::class);
        $this->assertDatabaseCount('users', 0);

        putenv('ADMIN_EMAIL=admin@sooqna.test');
        putenv('ADMIN_PASSWORD=StrongAdminPass#2026');
        try {
            $this->seed(AdminSeeder::class);
            $this->seed(AdminSeeder::class); // idempotent
        } finally {
            putenv('ADMIN_EMAIL');
            putenv('ADMIN_PASSWORD');
        }

        $this->assertDatabaseCount('users', 1);
        $admin = User::query()->where('email', 'admin@sooqna.test')->firstOrFail();
        $this->assertSame('admin', $admin->role);
        $this->assertNotNull($admin->email_verified_at);
        $this->assertTrue($admin->canAccessPanel(app(Panel::class)));
    }
}
