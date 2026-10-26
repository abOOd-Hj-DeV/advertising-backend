<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $user_id
 * @property string|null $legal_name
 * @property string $store_name
 * @property string|null $address
 * @property string $governorate
 * @property string $city
 * @property string $public_phone
 * @property string|null $public_email
 * @property string|null $register_number
 * @property bool $compliance_confirmed
 * @property string $status
 * @property array<string, mixed>|null $payload
 * @property string|null $review_note
 * @property string|null $verified_by
 * @property Carbon|null $verified_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class BusinessProfile extends Model
{
    use HasUlids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_SUSPENDED = 'suspended';

    /** الحقول التي يعيد تعديلها التحقق إلى pending */
    public const MATERIAL_FIELDS = [
        'legal_name', 'store_name', 'address', 'governorate', 'city',
        'public_phone', 'public_email', 'register_number',
    ];

    protected $fillable = [
        'legal_name', 'store_name', 'address', 'governorate', 'city',
        'public_phone', 'public_email', 'register_number',
        'compliance_confirmed', 'status', 'payload', 'review_note',
        'verified_by', 'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'compliance_confirmed' => 'boolean',
            'payload' => 'array',
            'verified_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
