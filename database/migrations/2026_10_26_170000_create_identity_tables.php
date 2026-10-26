<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_devices', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 10); // android | web | ios
            $table->text('fcm_token')->unique();
            $table->string('app_version')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->index('user_id');
        });

        Schema::create('user_consents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->string('purpose', 20); // terms | privacy | marketing | analytics
            $table->string('version');
            $table->boolean('granted');
            $table->string('ip_hash', 64)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'purpose']);
        });

        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);   // message | search_alert | listing_expiry | moderation | marketing
            $table->string('channel', 10); // push | email | in_app
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->unique(['user_id', 'type', 'channel']);
        });

        Schema::create('account_deletion_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('requested_at');
            $table->timestamp('scheduled_for');
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index('user_id');
        });

        Schema::create('business_profiles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('legal_name')->nullable();
            $table->string('store_name', 80);
            $table->string('address', 200)->nullable();
            $table->string('governorate');
            $table->string('city');
            $table->string('public_phone', 20);
            $table->string('public_email')->nullable();
            $table->string('register_number')->nullable();
            $table->boolean('compliance_confirmed')->default(false);
            $table->string('status', 12)->default('pending'); // pending | verified | rejected | suspended
            $table->jsonb('payload')->nullable();
            $table->string('review_note', 500)->nullable();
            $table->foreignUlid('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_profiles');
        Schema::dropIfExists('account_deletion_requests');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('user_consents');
        Schema::dropIfExists('user_devices');
    }
};
