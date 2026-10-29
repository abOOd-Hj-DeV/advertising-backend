<?php

declare(strict_types=1);

namespace App\Modules\Listings\Models;

use App\Modules\Catalog\Models\CategoryAttribute;
use App\Modules\Catalog\Models\CategoryAttributeOption;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $listing_id
 * @property string $attribute_id
 * @property string|null $option_id
 * @property string|null $value_text
 * @property float|null $value_number
 * @property bool|null $value_bool
 * @property-read CategoryAttribute $attribute
 * @property-read CategoryAttributeOption|null $option
 */
class ListingAttributeValue extends Model
{
    public $incrementing = false;

    protected $primaryKey = null;

    protected $table = 'listing_attribute_values';

    public $timestamps = false;

    protected $fillable = [
        'listing_id', 'attribute_id', 'option_id',
        'value_text', 'value_number', 'value_bool',
    ];

    protected function casts(): array
    {
        return [
            'value_number' => 'float',
            'value_bool' => 'boolean',
        ];
    }

    /** @return BelongsTo<Listing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /** @return BelongsTo<CategoryAttribute, $this> */
    public function attribute(): BelongsTo
    {
        return $this->belongsTo(CategoryAttribute::class, 'attribute_id');
    }

    /** @return BelongsTo<CategoryAttributeOption, $this> */
    public function option(): BelongsTo
    {
        return $this->belongsTo(CategoryAttributeOption::class, 'option_id');
    }

    /** قيمة العرض للـAPI */
    public function displayValue(): string
    {
        if ($this->option) {
            return $this->option->label_ar ?? $this->option->value;
        }
        if ($this->value_bool !== null) {
            return $this->value_bool ? 'true' : 'false';
        }
        if ($this->value_number !== null) {
            return rtrim(rtrim(number_format($this->value_number, 2, '.', ''), '0'), '.');
        }

        return (string) $this->value_text;
    }
}
