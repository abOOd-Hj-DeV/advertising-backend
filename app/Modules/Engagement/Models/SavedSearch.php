<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_id
 * @property string|null $name
 * @property string|null $query
 * @property array<string, mixed> $filters
 * @property bool $alerts_enabled
 * @property string $alert_frequency
 * @property Carbon|null $last_alerted_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class SavedSearch extends Model
{
    use HasUlids;

    public const MAX_PER_USER = 50;

    public const FREQ_INSTANT = 'instant';

    public const FREQ_DAILY = 'daily';

    protected $fillable = [
        'user_id', 'name', 'query', 'filters', 'alerts_enabled', 'alert_frequency', 'last_alerted_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'alerts_enabled' => 'boolean',
            'last_alerted_at' => 'datetime',
        ];
    }

    /** @return HasMany<SavedSearchMatch, $this> */
    public function matches(): HasMany
    {
        return $this->hasMany(SavedSearchMatch::class, 'saved_search_id');
    }
}
