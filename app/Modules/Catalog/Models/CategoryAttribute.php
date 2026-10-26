<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $category_id
 * @property string $key
 * @property string $label_ar
 * @property string $data_type
 * @property bool $is_required
 * @property bool $is_filterable
 * @property int $sort_order
 * @property-read Collection<int, CategoryAttributeOption> $options
 */
class CategoryAttribute extends Model
{
    use HasUlids;

    protected $fillable = [
        'category_id', 'key', 'label_ar', 'data_type',
        'is_required', 'is_filterable', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_filterable' => 'boolean',
        ];
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return HasMany<CategoryAttributeOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(CategoryAttributeOption::class, 'attribute_id')->orderBy('sort_order');
    }
}
