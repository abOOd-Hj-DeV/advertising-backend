<?php

declare(strict_types=1);

namespace App\Modules\Safety\Models;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    use HasUlids;

    public const TARGETS = ['listing', 'user', 'message'];

    public const REASONS = ['fraud', 'illegal', 'prohibited_item', 'harassment', 'spam', 'ip_violation', 'other'];

    public const STATUSES = ['open', 'in_review', 'actioned', 'dismissed'];

    protected $fillable = [
        'reporter_id', 'reporter_contact', 'target_type', 'target_id',
        'reason', 'details', 'evidence_snapshot', 'status',
        'assigned_to', 'resolved_at', 'resolution_note',
    ];

    protected function casts(): array
    {
        return ['evidence_snapshot' => 'array', 'resolved_at' => 'datetime'];
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }
}
