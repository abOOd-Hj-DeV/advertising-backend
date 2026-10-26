<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property string $id
 * @property string $email
 * @property string $password
 * @property string $display_name
 * @property string $account_type
 * @property string $role
 * @property string $status
 * @property Carbon|null $email_verified_at
 * @property string|null $default_location_id
 * @property string $locale
 * @property Carbon|null $last_login_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read BusinessProfile|null $businessProfile
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasUlids, Notifiable, SoftDeletes;

    public const ACCOUNT_PRIVATE = 'private';

    public const ACCOUNT_COMMERCIAL = 'commercial';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_PENDING_DELETION = 'pending_deletion';

    public const STATUS_DELETED = 'deleted';

    protected $fillable = [
        'email',
        'password',
        'display_name',
        'account_type',
        'default_location_id',
        'locale',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isCommercial(): bool
    {
        return $this->account_type === self::ACCOUNT_COMMERCIAL;
    }

    public function isVerifiedBusiness(): bool
    {
        return $this->businessProfile()
            ->where('status', BusinessProfile::STATUS_VERIFIED)
            ->exists();
    }

    public function hasVerifiedEmail(): bool
    {
        return $this->email_verified_at !== null;
    }

    /** @return HasOne<BusinessProfile, $this> */
    public function businessProfile(): HasOne
    {
        return $this->hasOne(BusinessProfile::class);
    }

    /** @return HasMany<UserDevice, $this> */
    public function devices(): HasMany
    {
        return $this->hasMany(UserDevice::class);
    }

    /** @return HasMany<UserConsent, $this> */
    public function consents(): HasMany
    {
        return $this->hasMany(UserConsent::class);
    }

    /** @return HasMany<NotificationPreference, $this> */
    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class);
    }

    /** @return HasMany<AccountDeletionRequest, $this> */
    public function deletionRequests(): HasMany
    {
        return $this->hasMany(AccountDeletionRequest::class);
    }
}
