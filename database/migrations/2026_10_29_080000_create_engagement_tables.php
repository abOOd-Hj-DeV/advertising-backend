<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favorites', function (Blueprint $table): void {
            $table->ulid('user_id');
            $table->ulid('listing_id');
            $table->timestamp('created_at')->useCurrent();

            $table->primary(['user_id', 'listing_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('listing_id')->references('id')->on('listings')->cascadeOnDelete();
            $table->index('listing_id');
        });

        Schema::create('saved_searches', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('user_id')->index();
            $table->string('name', 60)->nullable();
            $table->string('query', 100)->nullable();
            $table->jsonb('filters');
            $table->boolean('alerts_enabled')->default(true);
            $table->string('alert_frequency', 10)->default('instant'); // instant|daily
            $table->timestamp('last_alerted_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('saved_search_matches', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('saved_search_id')->index();
            $table->ulid('listing_id');
            $table->timestamp('matched_at')->useCurrent();
            $table->timestamp('notified_at')->nullable();

            $table->unique(['saved_search_id', 'listing_id']);
            $table->foreign('saved_search_id')->references('id')->on('saved_searches')->cascadeOnDelete();
            $table->foreign('listing_id')->references('id')->on('listings')->cascadeOnDelete();
        });

        Schema::create('follows', function (Blueprint $table): void {
            $table->ulid('follower_id');
            $table->ulid('seller_id');
            $table->timestamp('created_at')->useCurrent();

            $table->primary(['follower_id', 'seller_id']);
            $table->foreign('follower_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('seller_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index('seller_id');
        });

        Schema::create('recently_viewed', function (Blueprint $table): void {
            $table->ulid('user_id');
            $table->ulid('listing_id');
            $table->timestamp('viewed_at');

            $table->primary(['user_id', 'listing_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('listing_id')->references('id')->on('listings')->cascadeOnDelete();
            $table->index(['user_id', 'viewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recently_viewed');
        Schema::dropIfExists('follows');
        Schema::dropIfExists('saved_search_matches');
        Schema::dropIfExists('saved_searches');
        Schema::dropIfExists('favorites');
    }
};
