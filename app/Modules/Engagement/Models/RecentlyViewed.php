<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Models;

use App\Modules\Listings\Models\Listing;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $user_id
 * @property string $listing_id
 * @property Carbon $viewed_at
 */
class RecentlyViewed extends Model
{
    public const UPDATED_AT = null;

    public const CREATED_AT = null;

    public const MAX_PER_USER = 30;

    public $incrementing = false;

    protected $primaryKey = null;

    protected $table = 'recently_viewed';

    protected $fillable = ['user_id', 'listing_id', 'viewed_at'];

    /** @return BelongsTo<Listing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class, 'listing_id');
    }
}
