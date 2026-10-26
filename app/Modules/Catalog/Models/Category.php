<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string|null $parent_id
 * @property string $slug
 * @property string $name_ar
 * @property string|null $icon
 * @property int $sort_order
 * @property bool $is_active
 * @property bool $allows_shipping
 * @property bool $allows_wanted
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, Category> $children
 * @property-read Collection<int, CategoryAttribute> $attributes
 */
class Category extends Model
{
    use HasUlids;

    public const DATA_TYPES = ['enum', 'int', 'decimal', 'bool', 'text'];

    protected $fillable = [
        'parent_id', 'slug', 'name_ar', 'icon', 'sort_order',
        'is_active', 'allows_shipping', 'allows_wanted',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'allows_shipping' => 'boolean',
            'allows_wanted' => 'boolean',
        ];
    }

    /** @return BelongsTo<Category, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /** @return HasMany<Category, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort_order');
    }

    /** @return HasMany<CategoryAttribute, $this> */
    public function attributes(): HasMany
    {
        return $this->hasMany(CategoryAttribute::class)->orderBy('sort_order');
    }
}
