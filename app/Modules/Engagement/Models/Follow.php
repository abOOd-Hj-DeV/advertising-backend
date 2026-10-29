<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $follower_id
 * @property string $seller_id
 * @property Carbon $created_at
 */
class Follow extends Model
{
    public const UPDATED_AT = null;

    public $incrementing = false;

    protected $primaryKey = null;

    protected $fillable = ['follower_id', 'seller_id', 'created_at'];
}
