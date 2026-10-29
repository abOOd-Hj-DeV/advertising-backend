<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $conversation_id
 * @property string $sender_id
 * @property string $body
 * @property string $client_message_id
 * @property string $moderation_state
 * @property Carbon $created_at
 */
class Message extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    public const BODY_MAX = 2000;

    protected $fillable = [
        'conversation_id', 'sender_id', 'body', 'client_message_id', 'moderation_state', 'created_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Conversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }
}
