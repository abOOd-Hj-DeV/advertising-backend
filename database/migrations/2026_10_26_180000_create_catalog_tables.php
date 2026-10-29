<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('parent_id')->nullable();
            $table->string('slug')->unique();
            $table->string('name_ar', 80);
            $table->string('icon', 60)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('allows_shipping')->default(true);
            $table->boolean('allows_wanted')->default(true);
            $table->timestamps();
            $table->index(['parent_id', 'is_active']);
        });

        // Self-referencing FK must be added after the PK exists: on Postgres a
        // fluent constrained() runs before the implied primary-key command.
        Schema::table('categories', function (Blueprint $table): void {
            $table->foreign('parent_id')->references('id')->on('categories')->nullOnDelete();
        });

        Schema::create('category_attributes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('category_id')->constrained()->cascadeOnDelete();
            $table->string('key', 60);
            $table->string('label_ar', 80);
            $table->string('data_type', 10); // enum|int|decimal|bool|text
            $table->boolean('is_required')->default(false);
            $table->boolean('is_filterable')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['category_id', 'key']);
        });

        Schema::create('category_attribute_options', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('attribute_id')->constrained('category_attributes')->cascadeOnDelete();
            $table->string('value', 80);
            $table->string('label_ar', 120);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['attribute_id', 'value']);
        });

        Schema::create('locations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('governorate', 60);
            $table->string('city', 80);
            $table->string('district', 80)->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->timestamps();
            $table->index(['governorate', 'city']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
        Schema::dropIfExists('category_attribute_options');
        Schema::dropIfExists('category_attributes');
        Schema::dropIfExists('categories');
    }
};
