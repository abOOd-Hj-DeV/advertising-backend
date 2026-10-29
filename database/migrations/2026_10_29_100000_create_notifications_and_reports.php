<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->ulid('notifiable_id');
            $table->string('notifiable_type')->default('');
            $table->jsonb('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['notifiable_id', 'read_at', 'created_at']);
        });

        Schema::create('reports', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('reporter_id')->nullable();
            $table->string('reporter_contact')->nullable();
            $table->string('target_type'); // listing|user|message
            $table->ulid('target_id');
            $table->string('reason'); // fraud|illegal|prohibited_item|harassment|spam|ip_violation|other
            $table->text('details')->nullable();
            $table->jsonb('evidence_snapshot')->nullable();
            $table->string('status')->default('open'); // open|in_review|actioned|dismissed
            $table->ulid('assigned_to')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamps();
            $table->foreign('reporter_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('assigned_to')->references('id')->on('users')->nullOnDelete();
            $table->index(['status', 'created_at']);
            $table->index(['target_type', 'target_id']);
        });
    }
};
