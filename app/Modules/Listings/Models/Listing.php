<?php

declare(strict_types=1);

namespace App\Modules\Listings\Models;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Location;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_id
 * @property string $category_id
 * @property string $offer_type
 * @property string $title
 * @property string $description
 * @property int|null $price_cents
 * @property string $currency
 * @property string $price_type
 * @property string $status
 * @property string $location_id
 * @property float|null $lat
 * @property float|null $lng
 * @property bool $shipping_available
 * @property string|null $carrier
 * @property Carbon|null $published_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $reserved_at
 * @property Carbon|null $sold_at
 * @property int $views_count
 * @property int $favorites_count
 * @property int $version
 * @property string $moderation_state
 * @property Carbon|null $deleted_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 * @property-read Category $category
 * @property-read Location $location
 * @property-read Collection<int, Media> $media
 * @property-read Collection<int, ListingImage> $listingImages
 * @property-read Collection<int, ListingAttributeValue> $attributeValues
 */
class Listing extends Model
{
    use HasUlids, SoftDeletes;

    public const OFFER = 'offer';

    public const WANTED = 'wanted';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_RESERVED = 'reserved';

    public const STATUS_SOLD = 'sold';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_BLOCKED = 'blocked';

    public const STATUS_DELETED = 'deleted';

    public const PUBLIC_STATUSES = [self::STATUS_ACTIVE, self::STATUS_RESERVED];

    public const MODERATION_CLEAN = 'clean';

    public const MODERATION_FLAGGED = 'flagged';

    public const MODERATION_REVIEW = 'under_review';

    public const EXPIRY_DAYS = 60;

    public const TITLE_MAX = 70;

    public const DESCRIPTION_MAX = 4000;

    public const MAX_IMAGES = 10;

    protected $fillable = [
        'user_id', 'category_id', 'offer_type', 'title', 'description',
        'price_cents', 'currency', 'price_type', 'status', 'location_id',
        'lat', 'lng', 'shipping_available', 'carrier',
        'published_at', 'expires_at', 'reserved_at', 'sold_at',
        'views_count', 'favorites_count', 'version', 'moderation_state',
    ];

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'lat' => 'float',
            'lng' => 'float',
            'shipping_available' => 'boolean',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
            'reserved_at' => 'datetime',
            'sold_at' => 'datetime',
            'views_count' => 'integer',
            'favorites_count' => 'integer',
            'version' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** @return BelongsToMany<Media, $this> */
    public function media(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'listing_images', 'listing_id', 'media_id')
            ->withPivot(['position', 'is_cover'])
            ->orderByPivot('position');
    }

    /** @return HasMany<ListingImage, $this> */
    public function listingImages(): HasMany
    {
        return $this->hasMany(ListingImage::class)->orderBy('position');
    }

    /** @return HasMany<ListingAttributeValue, $this> */
    public function attributeValues(): HasMany
    {
        return $this->hasMany(ListingAttributeValue::class);
    }

    /** @return HasMany<ListingStatusEvent, $this> */
    public function statusEvents(): HasMany
    {
        return $this->hasMany(ListingStatusEvent::class);
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }

    public function isPubliclyVisible(): bool
    {
        return in_array($this->status, self::PUBLIC_STATUSES, true)
            && $this->moderation_state !== self::MODERATION_REVIEW
            && ! $this->trashed();
    }

    /** حالة الحالة + تسجيل الحدث ذرّيًا */
    public function transitionTo(string $to, ?User $actor, string $actorType, ?string $reason = null): void
    {
        $from = $this->status;
        $this->status = $to;

        match ($to) {
            self::STATUS_RESERVED => $this->reserved_at = now(),
            self::STATUS_SOLD => $this->sold_at = now(),
            self::STATUS_ACTIVE => null,
            default => null,
        };

        $this->version++;
        $this->save();

        $this->statusEvents()->create([
            'from_status' => $from,
            'to_status' => $to,
            'actor_id' => $actor?->id,
            'actor_type' => $actorType,
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }

    /** خريطة الانتقالات المسموحة لمالك الإعلان */
    public static function allowedOwnerTransitions(): array
    {
        return [
            'reserve' => [self::STATUS_ACTIVE => self::STATUS_RESERVED],
            'unreserve' => [self::STATUS_RESERVED => self::STATUS_ACTIVE],
            'sell' => [
                self::STATUS_ACTIVE => self::STATUS_SOLD,
                self::STATUS_RESERVED => self::STATUS_SOLD,
            ],
            'reactivate' => [
                self::STATUS_SOLD => self::STATUS_ACTIVE,
                self::STATUS_EXPIRED => self::STATUS_ACTIVE,
            ],
        ];
    }
}
