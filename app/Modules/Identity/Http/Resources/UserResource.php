<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Resources;

use App\Modules\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'display_name' => $this->display_name,
            'account_type' => $this->account_type,
            'status' => $this->status,
            'email_verified' => $this->email_verified_at !== null,
            'default_location' => null, // وحدة Catalog — تُملأ لاحقًا
            'business_status' => $this->businessProfile->status ?? 'none',
            'member_since' => $this->created_at->toDateString(),
        ];
    }
}
