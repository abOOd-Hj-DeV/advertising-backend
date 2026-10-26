<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $governorate
 * @property string $city
 * @property string|null $district
 * @property float|null $lat
 * @property float|null $lng
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Location extends Model
{
    use HasUlids;

    protected $fillable = ['governorate', 'city', 'district', 'lat', 'lng'];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
        ];
    }
}
