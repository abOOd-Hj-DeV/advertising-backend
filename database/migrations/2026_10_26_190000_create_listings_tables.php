<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('category_id')->constrained()->restrictOnDelete();
            $table->string('offer_type', 10); // offer|wanted
            $table->string('title', 70);
            $table->text('description');
            $table->unsignedBigInteger('price_cents')->nullable();
            $table->char('currency', 3)->default('SYP');
            $table->string('price_type', 10); // fixed|negotiable|free
            $table->string('status', 10)->default('active'); // active|reserved|sold|expired|blocked|deleted
            $table->foreignUlid('location_id')->constrained('locations')->restrictOnDelete();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->boolean('shipping_available')->default(false);
            $table->string('carrier', 60)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('reserved_at')->nullable();
            $table->timestamp('sold_at')->nullable();
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('favorites_count')->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->string('moderation_state', 15)->default('clean'); // clean|flagged|under_review
            $table->softDeletes();
            $table->timestamps();

            $table->index(['status', 'published_at']);
            $table->index(['category_id', 'status', 'published_at']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('listing_attribute_values', function (Blueprint $table): void {
            $table->foreignUlid('listing_id')->constrained('listings')->cascadeOnDelete();
            $table->foreignUlid('attribute_id')->constrained('category_attributes')->cascadeOnDelete();
            $table->foreignUlid('option_id')->nullable()->constrained('category_attribute_options')->cascadeOnDelete();
            $table->text('value_text')->nullable();
            $table->decimal('value_number', 14, 2)->nullable();
            $table->boolean('value_bool')->nullable();
            $table->primary(['listing_id', 'attribute_id']);
            $table->index(['attribute_id', 'option_id']);
        });

        Schema::create('media', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('disk', 20)->default('s3');
            $table->string('original_path');
            $table->string('mime', 20); // image/jpeg|image/png|image/webp
            $table->unsignedInteger('bytes');
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->char('sha256', 64)->index();
            $table->string('status', 12)->default('uploaded'); // uploaded|processing|ready|rejected
            $table->jsonb('variants')->nullable();
            $table->string('rejected_reason', 200)->nullable();
            $table->timestamps();
            $table->index(['owner_id', 'status']);
        });

        Schema::create('listing_images', function (Blueprint $table): void {
            $table->foreignUlid('listing_id')->constrained('listings')->cascadeOnDelete();
            $table->foreignUlid('media_id')->constrained('media')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->boolean('is_cover')->default(false);
            $table->primary(['listing_id', 'media_id']);
            $table->unique(['listing_id', 'position']);
        });

        Schema::create('listing_drafts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('listing_id')->nullable()->constrained('listings')->cascadeOnDelete();
            $table->jsonb('payload');
            $table->jsonb('media_ids')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->index(['user_id', 'updated_at']);
        });

        Schema::create('listing_status_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('listing_id')->constrained('listings')->cascadeOnDelete();
            $table->string('from_status', 10)->nullable();
            $table->string('to_status', 10);
            $table->foreignUlid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_type', 10); // owner|system|moderator
            $table->string('reason', 200)->nullable();
            $table->timestamp('created_at');
            $table->index(['listing_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_status_events');
        Schema::dropIfExists('listing_drafts');
        Schema::dropIfExists('listing_images');
        Schema::dropIfExists('media');
        Schema::dropIfExists('listing_attribute_values');
        Schema::dropIfExists('listings');
    }
};
