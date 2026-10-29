<?php

namespace Tests\Feature;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Location;
use App\Modules\Identity\Models\User;
use App\Modules\Listings\Models\Listing;
use App\Modules\Listings\Models\ListingDraft;
use App\Modules\Listings\Models\Media;
use Carbon\Carbon;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ListingsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $categoryId;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Queue::fake();
        $this->seed(CatalogSeeder::class);
        $this->categoryId = Category::query()->where('slug', 'miscellaneous')->value('id');
        $this->location = Location::query()->where('governorate', 'دمشق')->firstOrFail();
        $this->user = User::factory()->create(['email_verified_at' => now()]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'category_id' => $this->categoryId,
            'offer_type' => 'offer',
            'title' => 'دراجة جبلية حالة ممتازة',
            'description' => 'استعمال خفيف، صيانة دورية، مع إضافات.',
            'price_cents' => 150000000,
            'price_type' => 'negotiable',
            'location_id' => $this->location->id,
        ], $overrides);
    }

    private function createListing(array $overrides = [], ?User $as = null): array
    {
        Sanctum::actingAs($as ?? $this->user);

        return $this->postJson('/api/v1/listings', $this->payload($overrides))
            ->assertCreated()
            ->json('data');
    }

    public function test_create_and_get_listing(): void
    {
        $l = $this->createListing();

        $this->assertSame('active', $l['status']);
        $this->assertSame('SYP', $l['currency']);
        $this->assertTrue($l['is_own']);
        $this->assertSame(1, $l['version']);
        $this->assertNotNull($l['expires_at']);

        // عرض المالك لا يزيد المشاهدات
        Sanctum::actingAs($this->user);
        $detail = $this->getJson("/api/v1/listings/{$l['id']}")->assertOk();
        $this->assertSame($l['title'], $detail->json('data.title'));
        $this->assertSame(0, $detail->json('data.views_count'));
        $this->assertSame("{$this->location->governorate} — {$this->location->city}", $detail->json('data.location_label'));

        // عرض ضيف يزيدها
        auth()->forgetGuards();
        $guest = $this->getJson("/api/v1/listings/{$l['id']}")->assertOk();
        $this->assertSame(1, $guest->json('data.views_count'));
        $this->assertFalse($guest->json('data.is_own'));

        $this->assertDatabaseHas('listing_status_events', ['listing_id' => $l['id'], 'to_status' => 'active']);
    }

    public function test_create_requires_auth_and_verified_email(): void
    {
        $this->postJson('/api/v1/listings', $this->payload())->assertUnauthorized();

        $unverified = User::factory()->create(['email_verified_at' => null]);
        Sanctum::actingAs($unverified);
        $this->postJson('/api/v1/listings', $this->payload())->assertForbidden();
    }

    public function test_idempotency_replays_create(): void
    {
        Sanctum::actingAs($this->user);
        $key = 'idem-'.uniqid();

        $first = $this->withHeaders(['Idempotency-Key' => $key])
            ->postJson('/api/v1/listings', $this->payload())
            ->assertCreated();

        $second = $this->withHeaders(['Idempotency-Key' => $key])
            ->postJson('/api/v1/listings', $this->payload())
            ->assertCreated()
            ->assertHeader('Idempotency-Replayed', 'true');

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertSame(1, Listing::count());
    }

    public function test_update_with_if_match_and_version_conflict(): void
    {
        $l = $this->createListing();
        Sanctum::actingAs($this->user);

        $this->patchJson("/api/v1/listings/{$l['id']}", $this->payload(['title' => 'عنوان جديد']), ['If-Match' => '99'])
            ->assertStatus(412);

        $updated = $this->patchJson("/api/v1/listings/{$l['id']}", $this->payload(['title' => 'عنوان جديد', 'price_type' => 'fixed']), ['If-Match' => (string) $l['version']])
            ->assertOk();

        $this->assertSame('عنوان جديد', $updated->json('data.title'));
        $this->assertSame($l['version'] + 1, $updated->json('data.version'));
    }

    public function test_update_forbidden_for_non_owner(): void
    {
        $l = $this->createListing();
        Sanctum::actingAs(User::factory()->create());

        $this->patchJson("/api/v1/listings/{$l['id']}", $this->payload(), ['If-Match' => '1'])
            ->assertForbidden();
    }

    public function test_status_transitions(): void
    {
        $l = $this->createListing();
        Sanctum::actingAs($this->user);

        $this->postJson("/api/v1/listings/{$l['id']}/reserve")->assertOk()->assertJsonPath('data.status', 'reserved');
        $this->postJson("/api/v1/listings/{$l['id']}/unreserve")->assertOk()->assertJsonPath('data.status', 'active');
        $this->postJson("/api/v1/listings/{$l['id']}/sell")->assertOk()->assertJsonPath('data.status', 'sold');

        $reactivated = $this->postJson("/api/v1/listings/{$l['id']}/reactivate")->assertOk();
        $this->assertSame('active', $reactivated->json('data.status'));

        $this->postJson("/api/v1/listings/{$l['id']}/unreserve")->assertStatus(409);
    }

    public function test_delete_is_soft_and_hidden_from_others(): void
    {
        $l = $this->createListing();

        Sanctum::actingAs($this->user);
        $this->deleteJson("/api/v1/listings/{$l['id']}")->assertNoContent();
        $this->getJson("/api/v1/listings/{$l['id']}")->assertOk()->assertJsonPath('data.status', 'deleted');

        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/v1/listings/{$l['id']}")->assertStatus(410);

        auth()->forgetGuards();
        $results = collect($this->getJson('/api/v1/listings')->json('data'))->pluck('id');
        $this->assertNotContains($l['id'], $results);
    }

    public function test_search_filters(): void
    {
        $cars = Category::query()->where('slug', 'cars')->first();
        $aleppoLoc = Location::query()->where('governorate', 'حلب')->value('id');

        $l1 = $this->createListing(['title' => 'BMW X5 نظيفة', 'price_cents' => 5000000]);
        $l2 = $this->createListing([
            'category_id' => $cars->id,
            'title' => 'كيا سيراتو',
            'price_cents' => 99000000,
            'attributes' => ['brand' => 'كيا', 'year' => 2018, 'model' => 'سيراتو'],
            'location_id' => $aleppoLoc,
        ]);
        $this->createListing(['offer_type' => 'wanted', 'title' => 'أبحث عن دراجة']);

        auth()->forgetGuards();

        $q = collect($this->getJson('/api/v1/listings?q='.urlencode('BMW'))->json('data'))->pluck('id');
        $this->assertContains($l1['id'], $q);
        $this->assertNotContains($l2['id'], $q);

        $vehicles = Category::query()->where('slug', 'vehicles')->value('id');
        $cats = collect($this->getJson("/api/v1/listings?category={$vehicles}")->json('data'))->pluck('id');
        $this->assertContains($l2['id'], $cats);
        $this->assertNotContains($l1['id'], $cats);

        $min = collect($this->getJson('/api/v1/listings?price_min=50000000')->json('data'))->pluck('id');
        $this->assertContains($l2['id'], $min);

        $gov = collect($this->getJson('/api/v1/listings?governorate='.urlencode('حلب'))->json('data'))->pluck('id');
        $this->assertContains($l2['id'], $gov);
        $this->assertNotContains($l1['id'], $gov);

        $wanted = collect($this->getJson('/api/v1/listings?offer_type=wanted')->json('data'));
        $this->assertSame(['أبحث عن دراجة'], $wanted->pluck('title')->all());

        $attr = collect($this->getJson('/api/v1/listings?attributes[brand]='.urlencode('كيا'))->json('data'))->pluck('id');
        $this->assertContains($l2['id'], $attr);
    }

    public function test_attributes_validation(): void
    {
        $cars = Category::query()->where('slug', 'cars')->first();
        Sanctum::actingAs($this->user);

        // خاصية مطلوبة ناقصة
        $this->postJson('/api/v1/listings', $this->payload(['category_id' => $cars->id, 'attributes' => ['brand' => 'كيا']]))
            ->assertUnprocessable()
            ->assertJsonPath('code', 'request.validation_failed');

        // خيار enum غير معروف
        $this->postJson('/api/v1/listings', $this->payload(['category_id' => $cars->id, 'attributes' => ['brand' => 'علامة-وهمية', 'model' => 'x', 'year' => 2020]]))
            ->assertUnprocessable();

        // صحيحة
        $l = $this->createListing(['category_id' => $cars->id, 'attributes' => ['brand' => 'كيا', 'model' => 'ريو', 'year' => 2015, 'fuel' => 'بنزين']]);
        Sanctum::actingAs($this->user);
        $this->assertSame('كيا', $this->getJson("/api/v1/listings/{$l['id']}")->json('data.attributes.brand'));
    }

    public function test_my_listings_and_stats(): void
    {
        $this->createListing();
        $l2 = $this->createListing(['title' => 'إعلان ثانٍ']);
        Sanctum::actingAs($this->user);
        $this->postJson("/api/v1/listings/{$l2['id']}/sell")->assertOk();

        $all = $this->getJson('/api/v1/me/listings')->assertOk();
        $this->assertCount(2, $all->json('data'));

        $sold = $this->getJson('/api/v1/me/listings?status=sold')->assertOk();
        $this->assertCount(1, $sold->json('data'));

        $stats = $this->getJson('/api/v1/me/listings/stats')->assertOk();
        $this->assertSame(1, $stats->json('counts.sold'));
        $this->assertSame(1, $stats->json('counts.active'));
    }

    public function test_media_upload_list_and_delete(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->user);

        $file = UploadedFile::fake()->image('photo.jpg', 1200, 900)->size(200);
        $up = $this->post('/api/v1/media', ['file' => $file])->assertStatus(202);
        $mediaId = $up->json('data.id');
        $this->assertSame('uploaded', $up->json('data.status'));

        $this->getJson("/api/v1/media/{$mediaId}")->assertOk();
        $this->deleteJson("/api/v1/media/{$mediaId}")->assertNoContent();
    }

    public function test_media_rejects_non_image(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->user);
        $bad = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');
        $this->post('/api/v1/media', ['file' => $bad])->assertUnprocessable();
    }

    public function test_media_delete_forbidden_when_attached(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->user);

        $file = UploadedFile::fake()->image('photo.jpg', 400, 400);
        $mediaId = $this->post('/api/v1/media', ['file' => $file])->json('data.id');
        Media::query()->find($mediaId)->update(['status' => 'ready', 'variants' => ['card' => 'x.jpg']]);

        $l = $this->createListing(['media_ids' => [$mediaId]]);
        Sanctum::actingAs($this->user);
        $this->deleteJson("/api/v1/media/{$mediaId}")->assertStatus(409);

        $detail = $this->getJson("/api/v1/listings/{$l['id']}")->assertOk();
        $this->assertTrue($detail->json('data.images.0.is_cover'));
    }

    public function test_draft_lifecycle(): void
    {
        Sanctum::actingAs($this->user);

        $d = $this->postJson('/api/v1/drafts', ['payload' => $this->payload(['title' => 'مسودة 1'])])->assertCreated();
        $draftId = $d->json('data.id');
        $this->assertNull($d->json('data.listing_id'));
        $this->assertSame(1, $d->json('data.version'));

        $this->putJson("/api/v1/drafts/{$draftId}", ['payload' => $this->payload(['title' => 'مسودة 2'])], ['If-Match' => '99'])->assertStatus(412);
        $d2 = $this->putJson("/api/v1/drafts/{$draftId}", ['payload' => $this->payload(['title' => 'مسودة 2'])], ['If-Match' => '1'])->assertOk();
        $this->assertSame(2, $d2->json('data.version'));

        $pub = $this->postJson("/api/v1/drafts/{$draftId}/publish")->assertCreated();
        $this->assertSame('مسودة 2', $pub->json('data.title'));
        $this->assertSame(0, ListingDraft::count());
    }

    public function test_extend(): void
    {
        $l = $this->createListing();
        Listing::query()->find($l['id'])->update(['expires_at' => now()->subDay()]);

        Sanctum::actingAs($this->user);
        $extended = $this->postJson("/api/v1/listings/{$l['id']}/extend")->assertOk();
        $this->assertGreaterThan(now()->addDays(59)->timestamp, Carbon::parse($extended->json('data.expires_at'))->timestamp);
    }

    public function test_similar_listings(): void
    {
        $l = $this->createListing();
        $s1 = $this->createListing(['title' => 'آخر مشابه']);
        $other = $this->createListing(['category_id' => Category::query()->where('slug', 'pets')->value('id')]);

        $similar = collect($this->getJson("/api/v1/listings/{$l['id']}/similar")->json('data'))->pluck('id');
        $this->assertContains($s1['id'], $similar);
        $this->assertNotContains($l['id'], $similar);
        $this->assertNotContains($other['id'], $similar);
    }
}
