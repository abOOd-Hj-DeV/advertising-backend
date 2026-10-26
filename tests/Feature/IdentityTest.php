<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Identity\Models\BusinessProfile;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Notifications\VerifyEmailToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class IdentityTest extends TestCase
{
    use RefreshDatabase;

    private function register(array $overrides = []): array
    {
        Notification::fake();

        $payload = [
            'email' => 'user@example.com',
            'password' => 'secret-password-10',
            'display_name' => 'مستخدم تجريبي',
            'account_type' => 'private',
            'accept_terms_version' => '2026-10',
        ];

        $response = $this->postJson('/api/v1/auth/register', array_merge($payload, $overrides));

        return [$response, $payload];
    }

    public function test_register_returns_token_and_records_consents(): void
    {
        [$response, $payload] = $this->register();

        $response->assertCreated()
            ->assertJsonStructure(['token', 'user' => ['id', 'email', 'display_name', 'account_type', 'business_status']])
            ->assertJsonPath('user.email', 'user@example.com')
            ->assertJsonPath('user.email_verified', false)
            ->assertJsonPath('user.business_status', 'none');

        $user = User::where('email', 'user@example.com')->firstOrFail();
        $this->assertDatabaseCount('user_consents', 2);
        $this->assertTrue($user->consents()->where('purpose', 'terms')->exists());
        Notification::assertSentTo($user, VerifyEmailToken::class);
    }

    public function test_register_rejects_duplicate_email_and_short_password(): void
    {
        $this->register();
        [$dup] = $this->register();
        $dup->assertUnprocessable()->assertJsonPath('code', 'request.validation_failed');

        Notification::fake();
        $this->postJson('/api/v1/auth/register', [
            'email' => 'x@example.com',
            'password' => 'short',
            'display_name' => 'أحمد',
            'account_type' => 'private',
            'accept_terms_version' => '2026-10',
        ])->assertUnprocessable();
    }

    public function test_email_verification_token_flow(): void
    {
        [$response] = $this->register();
        $user = User::where('email', 'user@example.com')->firstOrFail();

        // استخرج الرمز المخزن: نحاكي الإصدار يدويًا للحصول على الرمز الخام
        $token = $user->id.'.testtoken123';
        Cache::put(VerifyEmailToken::cacheKey($user->id), hash('sha256', $token), 3600);

        $this->postJson('/api/v1/auth/email/verify', ['token' => 'bad.token'])
            ->assertUnprocessable();

        $this->postJson('/api/v1/auth/email/verify', ['token' => $token])
            ->assertOk()->assertJsonPath('verified', true);

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_login_gates(): void
    {
        $this->register();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'user@example.com', 'password' => 'wrong-password-xx', 'device_name' => 'd',
        ])->assertUnprocessable();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'user@example.com', 'password' => 'secret-password-10', 'device_name' => 'Pixel 9',
        ])->assertOk()->assertJsonStructure(['token', 'user']);

        User::where('email', 'user@example.com')->update(['status' => 'suspended']);
        $this->postJson('/api/v1/auth/login', [
            'email' => 'user@example.com', 'password' => 'secret-password-10', 'device_name' => 'd',
        ])->assertForbidden();
    }

    public function test_me_update_and_profile(): void
    {
        [$response] = $this->register();
        $token = $response->json('token');

        $this->withToken($token)->getJson('/api/v1/me')
            ->assertOk()->assertJsonPath('data.display_name', 'مستخدم تجريبي');

        $this->withToken($token)->patchJson('/api/v1/me', ['display_name' => 'اسم جديد'])
            ->assertOk()->assertJsonPath('data.display_name', 'اسم جديد');
    }

    public function test_devices_upsert_and_delete(): void
    {
        [$response] = $this->register();
        $token = $response->json('token');

        $this->withToken($token)->postJson('/api/v1/me/devices', [
            'platform' => 'android', 'fcm_token' => 'tok-1', 'app_version' => '1.0',
        ])->assertCreated();

        $this->withToken($token)->postJson('/api/v1/me/devices', [
            'platform' => 'android', 'fcm_token' => 'tok-1', 'app_version' => '1.1',
        ])->assertCreated();
        $this->assertDatabaseCount('user_devices', 1);

        $this->withToken($token)->deleteJson('/api/v1/me/devices', ['fcm_token' => 'tok-1'])
            ->assertNoContent();
        $this->assertDatabaseCount('user_devices', 0);
    }

    public function test_consents_and_notification_preferences(): void
    {
        [$response] = $this->register();
        $token = $response->json('token');

        $this->withToken($token)->postJson('/api/v1/me/consents', [
            'purpose' => 'marketing', 'version' => '2026-10', 'granted' => true,
        ])->assertCreated();

        $this->withToken($token)->getJson('/api/v1/me/consents')
            ->assertOk()->assertJsonCount(3);

        $this->withToken($token)->putJson('/api/v1/me/notification-preferences', [
            ['type' => 'message', 'channel' => 'push', 'enabled' => false],
            ['type' => 'search_alert', 'channel' => 'email', 'enabled' => true],
        ])->assertOk()->assertJsonCount(2);

        $this->assertDatabaseHas('notification_preferences', [
            'type' => 'message', 'channel' => 'push', 'enabled' => false,
        ]);
    }

    public function test_business_profile_lifecycle(): void
    {
        [$response] = $this->register(['account_type' => 'commercial', 'email' => 'shop@example.com']);
        $token = $response->json('token');

        $this->withToken($token)->getJson('/api/v1/me/business/status')
            ->assertOk()->assertJsonPath('status', 'none');

        $this->withToken($token)->putJson('/api/v1/me/business', [
            'store_name' => 'متجر حلب',
            'governorate' => 'حلب',
            'city' => 'حلب',
            'public_phone' => '+963900000000',
            'compliance_confirmed' => true,
        ])->assertOk()->assertJsonPath('data.status', 'pending');

        $this->withToken($token)->getJson('/api/v1/me/business')
            ->assertOk()->assertJsonPath('data.store_name', 'متجر حلب');

        // بعد التوثيق، تعديل جوهري يعيد pending
        $profile = BusinessProfile::firstOrFail();
        $profile->update(['status' => 'verified', 'verified_at' => now()]);

        $this->withToken($token)->putJson('/api/v1/me/business', [
            'store_name' => 'متجر حلب الجديد',
            'governorate' => 'حلب',
            'city' => 'حلب',
            'public_phone' => '+963900000000',
            'compliance_confirmed' => true,
        ])->assertOk()->assertJsonPath('data.status', 'pending');
    }

    public function test_deletion_request_and_cancel(): void
    {
        [$response] = $this->register();
        $token = $response->json('token');

        $this->withToken($token)->postJson('/api/v1/me/deletion')->assertAccepted();
        $this->assertEquals('pending_deletion', User::first()->status);
        $this->assertDatabaseHas('account_deletion_requests', ['scheduled_for' => now()->addDays(30)->toDateTimeString()]);

        // التوكنات القديمة أُبطلت — إعادة الدخول مسموحة للإلغاء
        $this->postJson('/api/v1/auth/login', [
            'email' => 'user@example.com', 'password' => 'secret-password-10', 'device_name' => 'd',
        ])->assertOk();
        $token2 = $this->postJson('/api/v1/auth/login', [
            'email' => 'user@example.com', 'password' => 'secret-password-10', 'device_name' => 'd',
        ])->json('token');

        $this->withToken($token2)->deleteJson('/api/v1/me/deletion')->assertNoContent();
        $this->assertEquals('active', User::first()->status);
    }

    public function test_logout_revokes_current_token(): void
    {
        [$response] = $this->register();
        $token = $response->json('token');

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->assertCount(0, User::first()->tokens()->get());
    }
}
