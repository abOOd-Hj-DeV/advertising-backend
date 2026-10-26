<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $attribute_id
 * @property string $value
 * @property string $label_ar
 * @property int $sort_order
 */
class CategoryAttributeOption extends Model
{
    use HasUlids;

    protected $fillable = ['attribute_id', 'value', 'label_ar', 'sort_order'];

    /** @return BelongsTo<CategoryAttribute, $this> */
    public function attribute(): BelongsTo
    {
        return $this->belongsTo(CategoryAttribute::class, 'attribute_id');
    }
}
