<?php

declare(strict_types=1);

namespace App\Modules\Safety\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $blocker_id
 * @property string $blocked_id
 * @property Carbon $created_at
 */
class UserBlock extends Model
{
    public const UPDATED_AT = null;

    public $incrementing = false;

    protected $primaryKey = null;

    protected $table = 'user_blocks';

    protected $fillable = ['blocker_id', 'blocked_id', 'created_at'];

    /** حظر فعّال بأي اتجاه بين المستخدمين */
    public static function blockedEitherWay(string $a, string $b): bool
    {
        return self::query()
            ->where(function ($q) use ($a, $b): void {
                $q->where('blocker_id', $a)->where('blocked_id', $b);
            })
            ->orWhere(function ($q) use ($a, $b): void {
                $q->where('blocker_id', $b)->where('blocked_id', $a);
            })
            ->exists();
    }
}
