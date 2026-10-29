<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $conversation_id
 * @property string $user_id
 * @property string|null $last_read_message_id
 * @property Carbon|null $archived_at
 * @property Carbon|null $deleted_at
 * @property int $unread_count
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ConversationParticipant extends Model
{
    public $incrementing = false;

    protected $primaryKey = null;

    protected $fillable = [
        'conversation_id', 'user_id', 'last_read_message_id',
        'archived_at', 'deleted_at', 'unread_count',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
            'deleted_at' => 'datetime',
            'unread_count' => 'integer',
        ];
    }
}
