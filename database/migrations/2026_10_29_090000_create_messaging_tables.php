<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('listing_id');
            $table->ulid('seller_id');
            $table->ulid('buyer_id');
            $table->string('listing_title_snapshot', 70);
            $table->ulid('last_message_id')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->unique(['listing_id', 'buyer_id']);
            $table->foreign('listing_id')->references('id')->on('listings')->restrictOnDelete();
            $table->foreign('seller_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('buyer_id')->references('id')->on('users')->restrictOnDelete();
            $table->index('last_message_at');
        });

        Schema::create('conversation_participants', function (Blueprint $table): void {
            $table->ulid('conversation_id');
            $table->ulid('user_id');
            $table->ulid('last_read_message_id')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamps();

            $table->primary(['conversation_id', 'user_id']);
            $table->foreign('conversation_id')->references('id')->on('conversations')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['user_id', 'archived_at']);
        });

        Schema::create('messages', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('conversation_id');
            $table->ulid('sender_id');
            $table->string('body', 2000);
            $table->ulid('client_message_id');
            $table->string('moderation_state', 10)->default('clean'); // clean|flagged|deleted
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['sender_id', 'client_message_id']);
            $table->foreign('conversation_id')->references('id')->on('conversations')->cascadeOnDelete();
            $table->foreign('sender_id')->references('id')->on('users')->restrictOnDelete();
            $table->index(['conversation_id', 'id']);
        });

        Schema::create('user_blocks', function (Blueprint $table): void {
            $table->ulid('blocker_id');
            $table->ulid('blocked_id');
            $table->timestamp('created_at')->useCurrent();

            $table->primary(['blocker_id', 'blocked_id']);
            $table->foreign('blocker_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('blocked_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index('blocked_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_blocks');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversation_participants');
        Schema::dropIfExists('conversations');
    }
};
