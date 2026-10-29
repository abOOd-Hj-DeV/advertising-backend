<?php

declare(strict_types=1);

namespace App\Modules\Listings\Http\Resources;

use App\Modules\Listings\Models\Listing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Listing */
class ListingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $card = (new ListingCardResource($this->resource))->toArray($request);

        return $card + [
            'description' => $this->description,
            'images' => $this->relationLoaded('media')
                ? $this->media->map(fn ($m) => [
                    'id' => $m->id,
                    'status' => $m->status,
                    'is_cover' => (bool) $m->pivot?->getAttribute('is_cover'),
                    'variants' => $m->variants,
                    'urls' => [
                        'thumb' => $m->urlFor('thumb'),
                        'card' => $m->urlFor('card'),
                        'full' => $m->urlFor('full'),
                        'original' => $m->publicUrl(),
                    ],
                    'width' => $m->width,
                    'height' => $m->height,
                ])->all()
                : [],
            'attributes' => $this->relationLoaded('attributeValues')
                ? $this->attributeValues->mapWithKeys(fn ($v) => [
                    $v->attribute->key => $v->displayValue(),
                ])->all()
                : [],
            'shipping_available' => $this->shipping_available,
            'carrier' => $this->carrier,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'location' => $this->relationLoaded('location') ? [
                'id' => $this->location->id,
                'governorate' => $this->location->governorate,
                'city' => $this->location->city,
                'district' => $this->location->district,
            ] : null,
            'seller' => $this->relationLoaded('user') ? [
                'id' => $this->user->id,
                'display_name' => $this->user->display_name,
                'account_type' => $this->user->account_type,
                'member_since' => $this->user->created_at->toDateString(),
                'business' => $this->user->relationLoaded('businessProfile') && $this->user->businessProfile
                    ? [
                        'store_name' => $this->user->businessProfile->store_name,
                        'verified' => $this->user->businessProfile->status === 'verified',
                        'governorate' => $this->user->businessProfile->governorate,
                        'city' => $this->user->businessProfile->city,
                    ]
                    : null,
            ] : null,
            'views_count' => $this->views_count,
            'is_favorite' => $user ? (bool) ($this->is_favorite ?? false) : false,
            'is_own' => $user ? $this->user_id === $user->id : false,
            'version' => $this->version,
            'published_at' => $this->published_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'reserved_at' => $this->reserved_at?->toIso8601String(),
            'sold_at' => $this->sold_at?->toIso8601String(),
        ];
    }
}
