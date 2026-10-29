<?php

declare(strict_types=1);

namespace App\Modules\Platform\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $key
 * @property string $user_id
 * @property string $endpoint
 * @property string $request_hash
 * @property int|null $response_status
 * @property array<string, mixed>|null $response_body
 * @property Carbon $expires_at
 */
class IdempotencyKey extends Model
{
    public const TTL_HOURS = 24;

    public $incrementing = false;

    protected $primaryKey = 'key';

    protected $fillable = [
        'key', 'user_id', 'endpoint', 'request_hash',
        'response_status', 'response_body', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'response_body' => 'array',
            'expires_at' => 'datetime',
        ];
    }
}
