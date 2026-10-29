<?php

declare(strict_types=1);

namespace App\Modules\Safety\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class ModerationAction extends Model
{
    use HasUlids;

    public const ACTIONS = [
        'block_listing', 'restore_listing', 'suspend_user', 'restore_user',
        'warn', 'delete_message', 'verify_business', 'reject_business',
    ];

    protected $fillable = [
        'moderator_id', 'target_type', 'target_id', 'action',
        'reason_code', 'statement_of_reasons', 'report_id',
    ];
}
