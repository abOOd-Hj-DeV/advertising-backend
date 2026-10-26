<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_id
 * @property string $type
 * @property string $channel
 * @property bool $enabled
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class NotificationPreference extends Model
{
    use HasUlids;

    public const TYPES = ['message', 'search_alert', 'listing_expiry', 'moderation', 'marketing'];

    public const CHANNELS = ['push', 'email', 'in_app'];

    protected $fillable = ['type', 'channel', 'enabled'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
