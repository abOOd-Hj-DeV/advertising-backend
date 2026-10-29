<?php

declare(strict_types=1);

namespace App\Modules\Safety\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    protected $fillable = ['actor_id', 'action', 'subject_type', 'subject_id', 'changes', 'ip_hash'];

    protected function casts(): array
    {
        return ['changes' => 'array', 'created_at' => 'datetime'];
    }

    /** تسجيل فعل إشرافي بقيمة تجزئة الـIP (لا تخزّن الـIP خامًا) */
    public static function record(?string $actorId, string $action, string $subjectType, ?string $subjectId, array $changes = []): void
    {
        self::query()->create([
            'actor_id' => $actorId,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'changes' => $changes,
            'ip_hash' => request()->ip() ? hash('sha256', request()->ip().config('app.key')) : null,
        ]);
    }
}
