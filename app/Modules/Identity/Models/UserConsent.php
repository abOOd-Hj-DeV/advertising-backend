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
 * @property string $purpose
 * @property string $version
 * @property bool $granted
 * @property string|null $ip_hash
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class UserConsent extends Model
{
    use HasUlids;

    public const PURPOSES = ['terms', 'privacy', 'marketing', 'analytics'];

    protected $fillable = ['purpose', 'version', 'granted', 'ip_hash'];

    protected function casts(): array
    {
        return ['granted' => 'boolean'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
