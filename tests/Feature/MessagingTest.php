<?php

namespace Tests\Feature;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Location;
use App\Modules\Identity\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MessagingTest extends TestCase
{
    use RefreshDatabase;

    private User $seller;

    private User $buyer;

    private string $listingId;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Queue::fake();
        $this->seed(CatalogSeeder::class);
        $this->seller = User::factory()->create(['email_verified_at' => now()]);
        $this->buyer = User::factory()->create(['email_verified_at' => now()]);

        Sanctum::actingAs($this->seller);
        $this->listingId = $this->postJson('/api/v1/listings', [
            'category_id' => Category::query()->where('slug', 'miscellaneous')->value('id'),
            'offer_type' => 'offer',
            'title' => 'دراجة جبلية',
            'description' => 'وصف',
            'price_cents' => 100,
            'price_type' => 'fixed',
            'location_id' => Location::query()->value('id'),
        ])->assertCreated()->json('data.id');
    }

    public function test_conversation_lifecycle(): void
    {
        Sanctum::actingAs($this->buyer);
        $conv = $this->postJson('/api/v1/conversations', [
            'listing_id' => $this->listingId,
            'first_message' => 'مرحبًا، ما زالت متوفرة؟',
        ])->assertCreated()->json('data');

        $this->assertSame('دراجة جبلية', $conv['listing']['title']);
        $this->assertSame($this->seller->id, $conv['participant']['id']);
        $this->assertSame('مرحبًا، ما زالت متوفرة؟', $conv['last_message']['body']);
        $this->assertFalse($conv['archived']);

        // إعادة POST لنفس الإعلان → نفس المحادثة (unique)
        $again = $this->postJson('/api/v1/conversations', ['listing_id' => $this->listingId])->assertCreated();
        $this->assertSame($conv['id'], $again->json('data.id'));

        // البائع يرى المحادثة + عدّاد غير مقروء = 1
        Sanctum::actingAs($this->seller);
        $sellerView = $this->getJson("/api/v1/conversations/{$conv['id']}")->assertOk();
        $this->assertSame(1, $sellerView->json('data.unread_count'));
        $this->assertSame($this->buyer->id, $sellerView->json('data.participant.id'));

        // أرشفة
        $this->patchJson("/api/v1/conversations/{$conv['id']}", ['archived' => true])->assertOk();
        $list = $this->getJson('/api/v1/conversations?archived=0')->json('data');
        $this->assertCount(0, $list);
        $this->assertCount(1, $this->getJson('/api/v1/conversations?archived=1')->json('data'));
        $this->patchJson("/api/v1/conversations/{$conv['id']}", ['archived' => false])->assertOk();

        // حذف من جهتي فقط
        $this->deleteJson("/api/v1/conversations/{$conv['id']}")->assertNoContent();
        $this->assertCount(0, $this->getJson('/api/v1/conversations')->json('data'));
        Sanctum::actingAs($this->buyer);
        $this->assertCount(1, $this->getJson('/api/v1/conversations')->json('data'));
    }

    public function test_cannot_message_own_listing_or_blocked(): void
    {
        Sanctum::actingAs($this->seller);
        $this->postJson('/api/v1/conversations', ['listing_id' => $this->listingId])->assertForbidden();

        // حظر بأي اتجاه يمنع الإنشاء
        Sanctum::actingAs($this->buyer);
        $this->putJson("/api/v1/blocks/{$this->seller->id}")->assertNoContent();
        $this->postJson('/api/v1/conversations', ['listing_id' => $this->listingId])->assertForbidden();
        $this->deleteJson("/api/v1/blocks/{$this->seller->id}")->assertNoContent();
    }

    public function test_messages_send_list_read_and_dedup(): void
    {
        Sanctum::actingAs($this->buyer);
        $conv = $this->postJson('/api/v1/conversations', ['listing_id' => $this->listingId])->json('data');

        $cmid = (string) Str::ulid();
        $m1 = $this->postJson("/api/v1/conversations/{$conv['id']}/messages", [
            'body' => 'السعر قابل للتفاوض؟',
            'client_message_id' => $cmid,
        ])->assertCreated()->json('data');
        $this->assertTrue($m1['is_mine']);

        // نفس client_message_id → نفس الرسالة، لا تكرار
        $dup = $this->postJson("/api/v1/conversations/{$conv['id']}/messages", [
            'body' => 'نص مختلف — لا يُدرج',
            'client_message_id' => $cmid,
        ])->assertCreated();
        $this->assertSame($m1['id'], $dup->json('data.id'));

        // ردّ البائع
        Sanctum::actingAs($this->seller);
        $m2 = $this->postJson("/api/v1/conversations/{$conv['id']}/messages", [
            'body' => 'نعم، قابل للتفاوض',
            'client_message_id' => (string) Str::ulid(),
        ])->assertCreated()->json('data');
        $this->assertTrue($m2['is_mine']); // is_mine بالنسبة للبائع مُرسل الرسالة
        $this->assertSame($this->seller->id, $m2['sender_id']);

        // قائمة الرسائل مرتبة صاعدًا
        $msgs = $this->getJson("/api/v1/conversations/{$conv['id']}/messages")->assertOk();
        $bodies = collect($msgs->json('data'))->pluck('body')->all();
        $this->assertSame(['السعر قابل للتفاوض؟', 'نعم، قابل للتفاوض'], $bodies);

        // polling بعد رسالة معينة
        $after = $this->getJson("/api/v1/conversations/{$conv['id']}/messages?after={$m1['id']}")->assertOk();
        $this->assertSame([$m2['id']], collect($after->json('data'))->pluck('id')->all());

        // markRead يصفّر العدّاد
        $this->postJson("/api/v1/conversations/{$conv['id']}/read", ['last_read_message_id' => $m2['id']])->assertNoContent();
        $convView = $this->getJson("/api/v1/conversations/{$conv['id']}")->json('data');
        $this->assertSame(0, $convView['unread_count']);
    }

    public function test_outsider_cannot_access_conversation(): void
    {
        Sanctum::actingAs($this->buyer);
        $conv = $this->postJson('/api/v1/conversations', ['listing_id' => $this->listingId])->json('data');

        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/v1/conversations/{$conv['id']}")->assertNotFound();
        $this->getJson("/api/v1/conversations/{$conv['id']}/messages")->assertNotFound();
        $this->postJson("/api/v1/conversations/{$conv['id']}/messages", [
            'body' => 'متطفّل', 'client_message_id' => (string) Str::ulid(),
        ])->assertNotFound();
    }

    public function test_block_list(): void
    {
        $other = User::factory()->create();
        Sanctum::actingAs($this->buyer);

        $this->putJson("/api/v1/blocks/{$other->id}")->assertNoContent();
        $this->putJson("/api/v1/blocks/{$other->id}")->assertNoContent(); // idempotent

        $list = $this->getJson('/api/v1/blocks')->assertOk();
        $this->assertSame([$other->id], collect($list->json('data'))->pluck('id')->all());

        $this->deleteJson("/api/v1/blocks/{$other->id}")->assertNoContent();
        $this->assertCount(0, $this->getJson('/api/v1/blocks')->json('data'));
    }
}
