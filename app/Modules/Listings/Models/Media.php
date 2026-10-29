<?php

declare(strict_types=1);

namespace App\Modules\Listings\Models;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property string $id
 * @property string $owner_id
 * @property string $disk
 * @property string $original_path
 * @property string $mime
 * @property int $bytes
 * @property int|null $width
 * @property int|null $height
 * @property string $sha256
 * @property string $status
 * @property array<string, string>|null $variants
 * @property string|null $rejected_reason
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Pivot|null $pivot
 */
class Media extends Model
{
    use HasUlids;

    public const STATUS_UPLOADED = 'uploaded';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_READY = 'ready';

    public const STATUS_REJECTED = 'rejected';

    public const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    public const MAX_BYTES = 8 * 1024 * 1024; // 8MB

    public const MAX_PIXELS = 25_000_000; // 25MP

    public const VARIANT_SIZES = ['thumb' => 300, 'card' => 800, 'full' => 1600];

    protected $table = 'media';

    protected $fillable = [
        'owner_id', 'disk', 'original_path', 'mime', 'bytes',
        'width', 'height', 'sha256', 'status', 'variants', 'rejected_reason',
    ];

    protected function casts(): array
    {
        return [
            'bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'variants' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return BelongsToMany<Listing, $this> */
    public function listings(): BelongsToMany
    {
        return $this->belongsToMany(Listing::class, 'listing_images', 'media_id', 'listing_id');
    }

    public function isAttachedToListing(): bool
    {
        return $this->listings()->exists();
    }

    /** URL عامة لنسخة معينة أو الأصل */
    public function urlFor(string $variant): ?string
    {
        $path = $this->variants[$variant] ?? null;
        if (! $path) {
            return null;
        }

        return $this->publicUrl($path);
    }

    public function publicUrl(?string $path = null): string
    {
        $path = $path ?? $this->original_path;

        if (config("filesystems.disks.{$this->disk}.driver") === 's3') {
            return Storage::disk($this->disk)->temporaryUrl($path, now()->addHours(24));
        }

        $base = rtrim((string) config("filesystems.disks.{$this->disk}.url", ''), '/');
        if ($base === '') {
            $base = rtrim((string) config('app.url'), '/').'/media-files';
        }

        return $base.'/'.ltrim($path, '/');
    }
}
