<?php

declare(strict_types=1);

namespace App\Modules\Listings\Models;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_id
 * @property string|null $listing_id
 * @property array<string, mixed> $payload
 * @property list<string>|null $media_ids
 * @property int $version
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ListingDraft extends Model
{
    use HasUlids;

    public const MAX_PER_USER = 50;

    protected $fillable = [
        'user_id', 'listing_id', 'payload', 'media_ids', 'version',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'media_ids' => 'array',
            'version' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Listing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }
}
