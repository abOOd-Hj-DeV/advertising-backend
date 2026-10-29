<?php

declare(strict_types=1);

namespace App\Modules\Listings\Http\Resources;

use App\Modules\Listings\Models\Listing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Listing */
class ListingCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $cover = $this->relationLoaded('media')
            ? $this->media->firstWhere('pivot.is_cover', true) ?? $this->media->first()
            : null;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'price_cents' => $this->price_cents,
            'currency' => $this->currency,
            'price_type' => $this->price_type,
            'offer_type' => $this->offer_type,
            'status' => $this->status,
            'cover_url' => $cover?->urlFor('card') ?? $cover?->publicUrl(),
            'location_label' => $this->relationLoaded('location')
                ? "{$this->location->governorate} — {$this->location->city}"
                : null,
            'governorate' => $this->relationLoaded('location') ? $this->location->governorate : null,
            'city' => $this->relationLoaded('location') ? $this->location->city : null,
            'district' => $this->relationLoaded('location') ? $this->location->district : null,
            'category_id' => $this->category_id,
            'category_name' => $this->relationLoaded('category') ? $this->category?->name_ar : null,
            'shipping_available' => (bool) $this->shipping_available,
            'seller_id' => $this->user_id,
            'seller_name' => $this->relationLoaded('user') ? $this->user->display_name : null,
            'seller_commercial' => $this->relationLoaded('user')
                ? $this->user->account_type === 'commercial'
                : null,
            'published_at' => $this->published_at?->toIso8601String(),
            'favorites_count' => $this->favorites_count,
        ];
    }
}
