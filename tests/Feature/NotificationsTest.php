<?php

namespace Tests\Feature;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Location;
use App\Modules\Identity\Models\User;
use App\Modules\Safety\Models\Report;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function seedNotification(User $user): string
    {
        $id = (string) Str::uuid();
        DB::table('notifications')->insert([
            'id' => $id,
            'type' => 'new_message',
            'notifiable_id' => $user->id,
            'notifiable_type' => User::class,
            'data' => json_encode(['conversation_id' => 'x', 'preview' => 'رسالة جديدة']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    public function test_notification_lifecycle(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $id1 = $this->seedNotification($user);
        $id2 = $this->seedNotification($user);

        $this->getJson('/api/v1/notifications/unread-count')->assertOk()->assertJson(['unread' => 2]);
        $list = $this->getJson('/api/v1/notifications')->assertOk();
        $this->assertCount(2, $list->json('data'));
        $this->assertSame('new_message', $list->json('data.0.type'));

        $this->postJson("/api/v1/notifications/{$id1}/read")->assertNoContent();
        $this->assertSame(1, $this->getJson('/api/v1/notifications/unread-count')->json('unread'));

        $this->postJson('/api/v1/notifications/read-all')->assertNoContent();
        $this->assertSame(0, $this->getJson('/api/v1/notifications/unread-count')->json('unread'));

        $this->deleteJson("/api/v1/notifications/{$id2}")->assertNoContent();
        $this->assertCount(1, $this->getJson('/api/v1/notifications')->json('data'));

        // إشعار مستخدم آخر غير مرئي لي
        $other = User::factory()->create();
        $oid = $this->seedNotification($other);
        $this->postJson("/api/v1/notifications/{$oid}/read")->assertNoContent();
        $this->assertSame(1, DB::table('notifications')->where('id', $oid)->whereNull('read_at')->count());
    }

    public function test_report_submission(): void
    {
        $this->seed(CatalogSeeder::class);
        $seller = User::factory()->create(['email_verified_at' => now()]);
        Sanctum::actingAs($seller);
        $listingId = $this->postJson('/api/v1/listings', [
            'category_id' => Category::query()->where('slug', 'miscellaneous')->value('id'),
            'offer_type' => 'offer',
            'title' => 'إعلان مشبوه',
            'description' => 'x',
            'price_cents' => 10,
            'price_type' => 'fixed',
            'location_id' => Location::query()->value('id'),
        ])->assertCreated()->json('data.id');

        // مستخدم مصرّح يبلّغ
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/v1/reports', [
            'target_type' => 'listing',
            'target_id' => $listingId,
            'reason' => 'fraud',
            'details' => 'يبدو احتيالًا',
        ])->assertStatus(202);

        $this->assertDatabaseHas('reports', [
            'target_id' => $listingId,
            'reason' => 'fraud',
            'status' => 'open',
        ]);
        $report = Report::query()->latest()->first();
        $this->assertSame('إعلان مشبوه', $report->evidence_snapshot['title']);

        // ضيف يبلّغ بوسيلة تواصل
        auth()->forgetGuards();
        $this->postJson('/api/v1/reports', [
            'target_type' => 'user',
            'target_id' => $seller->id,
            'reason' => 'spam',
            'reporter_contact' => 'guest@example.com',
        ])->assertStatus(202);

        // ضيف بلا وسيلة تواصل → 422
        $this->postJson('/api/v1/reports', [
            'target_type' => 'user',
            'target_id' => $seller->id,
            'reason' => 'spam',
        ])->assertUnprocessable();
    }
}
