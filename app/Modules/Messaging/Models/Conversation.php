<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Models;

use App\Modules\Identity\Models\User;
use App\Modules\Listings\Models\Listing;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $listing_id
 * @property string $seller_id
 * @property string $buyer_id
 * @property string $listing_title_snapshot
 * @property string|null $last_message_id
 * @property Carbon|null $last_message_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Listing $listing
 * @property-read User $seller
 * @property-read User $buyer
 */
class Conversation extends Model
{
    use HasUlids;

    protected $fillable = [
        'listing_id', 'seller_id', 'buyer_id', 'listing_title_snapshot',
        'last_message_id', 'last_message_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Listing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class, 'listing_id');
    }

    /** @return BelongsTo<User, $this> */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /** @return BelongsTo<User, $this> */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    /** @return HasMany<Message, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'conversation_id');
    }

    /** @return HasMany<ConversationParticipant, $this> */
    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class, 'conversation_id');
    }

    public function isParticipant(User $user): bool
    {
        return $this->seller_id === $user->id || $this->buyer_id === $user->id;
    }

    /** الطرف الآخر بالنسبة لـ$me */
    public function otherUserId(string $meId): string
    {
        return $this->seller_id === $meId ? $this->buyer_id : $this->seller_id;
    }
}
