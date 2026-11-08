<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * بحث نصي: pg_trgm + فهارس GIN على title/description ليعمل ILIKE '%q%' بفهرس.
 * يُطبَّق على PostgreSQL فقط؛ SQLite (الاختبارات المحلية) يتجاهله.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement('CREATE INDEX IF NOT EXISTS listings_title_trgm_idx ON listings USING gin (title gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS listings_description_trgm_idx ON listings USING gin (description gin_trgm_ops)');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS listings_title_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS listings_description_trgm_idx');
    }
};
