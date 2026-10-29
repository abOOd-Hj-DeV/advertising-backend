<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moderation_actions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('moderator_id');
            $table->string('target_type'); // listing|user|message|business_profile
            $table->ulid('target_id');
            $table->string('action'); // block_listing|restore_listing|suspend_user|restore_user|warn|delete_message|verify_business|reject_business
            $table->string('reason_code')->nullable();
            $table->text('statement_of_reasons')->nullable();
            $table->ulid('report_id')->nullable();
            $table->timestamps();
            $table->foreign('moderator_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('report_id')->references('id')->on('reports')->nullOnDelete();
            $table->index(['target_type', 'target_id']);
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('actor_id')->nullable();
            $table->string('action');
            $table->string('subject_type');
            $table->ulid('subject_id')->nullable();
            $table->jsonb('changes')->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['subject_type', 'subject_id']);
            $table->index('created_at');
        });
    }
};
