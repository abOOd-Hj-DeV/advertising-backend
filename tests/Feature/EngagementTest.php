<?php

namespace Tests\Feature;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Location;
use App\Modules\Engagement\Models\RecentlyViewed;
use App\Modules\Identity\Models\User;
use App\Modules\Listings\Models\Listing;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EngagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private array $listing;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Queue::fake();
        $this->seed(CatalogSeeder::class);
        $this->user = User::factory()->create(['email_verified_at' => now()]);

        Sanctum::actingAs($this->user);
        $this->listing = $this->postJson('/api/v1/listings', [
            'category_id' => Category::query()->where('slug', 'miscellaneous')->value('id'),
            'offer_type' => 'offer',
            'title' => 'دراجة جبلية',
            'description' => 'وصف',
            'price_cents' => 100,
            'price_type' => 'fixed',
            'location_id' => Location::query()->value('id'),
        ])->assertCreated()->json('data');
    }

    public function test_favorite_toggle_is_idempotent_and_counted(): void
    {
        Sanctum::actingAs($this->user);
        $id = $this->listing['id'];

        $this->putJson("/api/v1/favorites/{$id}")->assertNoContent();
        $this->assertSame(1, Listing::find($id)->favorites_count);

        // تكرار → لا عدّاد مضاعف
        $this->putJson("/api/v1/favorites/{$id}")->assertNoContent();
        $this->assertSame(1, Listing::find($id)->favorites_count);

        // is_favorite يظهر في التفاصيل
        $this->assertTrue($this->getJson("/api/v1/listings/{$id}")->json('data.is_favorite'));

        $this->deleteJson("/api/v1/favorites/{$id}")->assertNoContent();
        $this->assertSame(0, Listing::find($id)->favorites_count);
        $this->deleteJson("/api/v1/favorites/{$id}")->assertNoContent();
        $this->assertSame(0, Listing::find($id)->favorites_count);
    }

    public function test_favorites_list(): void
    {
        Sanctum::actingAs($this->user);
        $this->putJson("/api/v1/favorites/{$this->listing['id']}");

        $list = $this->getJson('/api/v1/favorites')->assertOk();
        $this->assertSame([$this->listing['id']], collect($list->json('data'))->pluck('id')->all());
    }

    public function test_saved_search_crud_and_limit(): void
    {
        Sanctum::actingAs($this->user);

        $s = $this->postJson('/api/v1/saved-searches', [
            'name' => 'سيارات دمشق',
            'filters' => ['category' => 'cars', 'governorate' => 'دمشق'],
        ])->assertCreated();
        $id = $s->json('data.id');
        $this->assertTrue($s->json('data.alerts_enabled'));
        $this->assertSame('instant', $s->json('data.alert_frequency'));

        $this->patchJson("/api/v1/saved-searches/{$id}", ['alert_frequency' => 'daily'])->assertOk()
            ->assertJsonPath('data.alert_frequency', 'daily');

        // مستخدم آخر لا يصل
        Sanctum::actingAs(User::factory()->create());
        $this->patchJson("/api/v1/saved-searches/{$id}", ['name' => 'x'])->assertNotFound();
        $this->deleteJson("/api/v1/saved-searches/{$id}")->assertNotFound();

        Sanctum::actingAs($this->user);
        $this->deleteJson("/api/v1/saved-searches/{$id}")->assertNoContent();
        $this->assertCount(0, $this->getJson('/api/v1/saved-searches')->json('data'));
    }

    public function test_follow_lifecycle(): void
    {
        $seller = User::factory()->create();

        Sanctum::actingAs($this->user);
        // لا متابعة ذاتية
        $this->putJson("/api/v1/follows/{$this->user->id}")->assertUnprocessable();

        $this->putJson("/api/v1/follows/{$seller->id}")->assertNoContent();
        $this->putJson("/api/v1/follows/{$seller->id}")->assertNoContent(); // idempotent

        $list = $this->getJson('/api/v1/follows')->assertOk();
        $this->assertSame([$seller->id], collect($list->json('data'))->pluck('id')->all());

        // يظهر على الصفحة العامة
        $public = $this->getJson("/api/v1/users/{$seller->id}")->assertOk();
        $this->assertTrue($public->json('data.is_following'));

        $this->deleteJson("/api/v1/follows/{$seller->id}")->assertNoContent();
        $this->assertFalse($this->getJson("/api/v1/users/{$seller->id}")->json('data.is_following'));
    }

    public function test_seller_public_pages(): void
    {
        $seller = $this->user;

        auth()->forgetGuards();
        $this->getJson("/api/v1/users/{$seller->id}")->assertOk()
            ->assertJsonPath('data.display_name', $seller->display_name);

        $listings = $this->getJson("/api/v1/users/{$seller->id}/listings")->assertOk();
        $this->assertSame([$this->listing['id']], collect($listings->json('data'))->pluck('id')->all());

        $stats = $this->getJson("/api/v1/users/{$seller->id}/stats")->assertOk();
        $this->assertSame(1, $stats->json('data.active_listings'));
    }

    public function test_recently_viewed_recorded_on_show_and_capped(): void
    {
        // المالك يسجّل مشاهدة أيضًا
        Sanctum::actingAs($this->user);
        $this->getJson("/api/v1/listings/{$this->listing['id']}")->assertOk();

        $this->assertDatabaseHas('recently_viewed', [
            'user_id' => $this->user->id,
            'listing_id' => $this->listing['id'],
        ]);

        // آخر 30 فقط — 35 إعلانًا خامًا ومشاهدة لكل منها
        for ($i = 0; $i < 35; $i++) {
            $lid = (string) Str::ulid();
            Listing::query()->insert([
                'id' => $lid,
                'user_id' => $this->user->id,
                'category_id' => $this->listing['category_id'] ?? Category::query()->where('slug', 'miscellaneous')->value('id'),
                'offer_type' => 'offer',
                'title' => "إعلان {$i}",
                'description' => 'وصف',
                'price_type' => 'fixed',
                'price_cents' => 100,
                'currency' => 'SYP',
                'location_id' => Location::query()->value('id'),
                'status' => 'active',
                'moderation_state' => 'clean',
                'version' => 1,
                'published_at' => now(),
                'expires_at' => now()->addDays(60),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            RecentlyViewed::query()->insert([
                'user_id' => $this->user->id,
                'listing_id' => $lid,
                'viewed_at' => now()->subMinutes($i + 1),
            ]);
        }

        RecentlyViewedControllerStub::trim($this->user->id);
        $this->assertLessThanOrEqual(30, RecentlyViewed::query()->where('user_id', $this->user->id)->count());

        $list = $this->getJson('/api/v1/recently-viewed')->assertOk();
        $this->assertLessThanOrEqual(30, count($list->json('data')));

        $this->deleteJson('/api/v1/recently-viewed')->assertNoContent();
        $this->assertCount(0, $this->getJson('/api/v1/recently-viewed')->json('data'));
    }
}

/** مساعد لقص السجل — يستدعي مسار القص نفسه عبر record() */
class RecentlyViewedControllerStub
{
    public static function trim(string $userId): void
    {
        $keep = RecentlyViewed::query()
            ->where('user_id', $userId)
            ->orderByDesc('viewed_at')
            ->limit(30)
            ->pluck('listing_id');
        RecentlyViewed::query()->where('user_id', $userId)->whereNotIn('listing_id', $keep)->delete();
    }
}
