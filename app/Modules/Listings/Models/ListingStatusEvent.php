<?php

declare(strict_types=1);

namespace App\Modules\Listings\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $listing_id
 * @property string|null $from_status
 * @property string $to_status
 * @property string|null $actor_id
 * @property string $actor_type
 * @property string|null $reason
 * @property Carbon $created_at
 */
class ListingStatusEvent extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    public const ACTOR_OWNER = 'owner';

    public const ACTOR_SYSTEM = 'system';

    public const ACTOR_MODERATOR = 'moderator';

    protected $fillable = [
        'listing_id', 'from_status', 'to_status',
        'actor_id', 'actor_type', 'reason', 'created_at',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /** @return BelongsTo<Listing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
}
