<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $saved_search_id
 * @property string $listing_id
 * @property Carbon $matched_at
 * @property Carbon|null $notified_at
 */
class SavedSearchMatch extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $fillable = ['saved_search_id', 'listing_id', 'matched_at', 'notified_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'matched_at' => 'datetime',
            'notified_at' => 'datetime',
        ];
    }
}
