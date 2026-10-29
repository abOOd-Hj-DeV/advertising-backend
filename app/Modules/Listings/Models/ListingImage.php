<?php

declare(strict_types=1);

namespace App\Modules\Listings\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $listing_id
 * @property string $media_id
 * @property int $position
 * @property bool $is_cover
 */
class ListingImage extends Model
{
    public $incrementing = false;

    protected $primaryKey = null;

    protected $table = 'listing_images';

    public $timestamps = false;

    protected $fillable = ['listing_id', 'media_id', 'position', 'is_cover'];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_cover' => 'boolean',
        ];
    }
}
