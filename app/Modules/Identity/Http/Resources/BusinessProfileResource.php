<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Resources;

use App\Modules\Identity\Models\BusinessProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BusinessProfile */
class BusinessProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'legal_name' => $this->legal_name,
            'store_name' => $this->store_name,
            'address' => $this->address,
            'governorate' => $this->governorate,
            'city' => $this->city,
            'public_phone' => $this->public_phone,
            'public_email' => $this->public_email,
            'register_number' => $this->register_number,
            'status' => $this->status,
            'review_note' => $this->review_note,
            'verified_at' => $this->verified_at?->toIso8601String(),
        ];
    }
}
