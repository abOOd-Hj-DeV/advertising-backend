<?php

namespace Tests\Feature;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Location;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_categories_tree_and_details(): void
    {
        $this->seed(CatalogSeeder::class);

        $tree = $this->getJson('/api/v1/categories')->assertOk();
        $roots = $tree->json('data');
        $this->assertNotEmpty($roots);

        $vehicles = collect($roots)->firstWhere('slug', 'vehicles');
        $this->assertSame('سيارات ومركبات', $vehicles['name']);
        $this->assertNotEmpty($vehicles['children']);

        $cars = collect($vehicles['children'])->firstWhere('slug', 'cars');
        $detail = $this->getJson("/api/v1/categories/{$cars['id']}")->assertOk();
        $attrs = collect($detail->json('data.attributes'));
        $brand = $attrs->firstWhere('key', 'brand');
        $this->assertTrue($brand['required']);
        $this->assertNotEmpty($brand['options']);
        $this->assertSame('enum', $brand['type']);

        // تصنيف أب: خصائصه الخاصة فقط
        $rootDetail = $this->getJson("/api/v1/categories/{$vehicles['id']}")->assertOk();
        $this->assertSame([], $rootDetail->json('data.inherited_attributes'));
    }

    public function test_category_not_found(): void
    {
        $this->getJson('/api/v1/categories/'.str_repeat('a', 26))
            ->assertStatus(404)
            ->assertJsonPath('code', 'resource.not_found');
    }

    public function test_locations_grouped_and_search(): void
    {
        $this->seed(CatalogSeeder::class);
        $this->assertGreaterThan(50, Location::count());

        $all = $this->getJson('/api/v1/locations')->assertOk();
        $govs = collect($all->json('data'))->pluck('governorate');
        $this->assertContains('دمشق', $govs);
        $this->assertContains('حلب', $govs);

        $search = $this->getJson('/api/v1/locations?q='.urlencode('جبلة'))->assertOk();
        $hits = $search->json('data');
        $this->assertNotEmpty($hits);
        $this->assertSame('جبلة', $hits[0]['city']);
        $this->assertSame('اللاذقية', $hits[0]['governorate']);

        $search2 = $this->getJson('/api/v1/locations?q='.urlencode('القامشلي'))->assertOk();
        $this->assertSame('الحسكة', $search2->json('data.0.governorate'));
    }

    public function test_category_inactive_hidden(): void
    {
        $this->seed(CatalogSeeder::class);
        $cat = Category::query()->where('slug', 'miscellaneous')->first();
        $cat->update(['is_active' => false]);

        $this->getJson("/api/v1/categories/{$cat->id}")->assertStatus(404);
        $slugs = collect($this->getJson('/api/v1/categories')->json('data'))->pluck('slug');
        $this->assertNotContains('miscellaneous', $slugs);
    }
}
