<?php

declare(strict_types=1);

namespace App\Modules\Listings\Services;

use App\Modules\Identity\Models\User;
use App\Modules\Listings\Http\Requests\ListingPayload;
use App\Modules\Listings\Models\Listing;
use App\Modules\Listings\Models\ListingAttributeValue;
use App\Modules\Listings\Models\ListingImage;
use App\Modules\Listings\Models\ListingStatusEvent;
use App\Modules\Listings\Models\Media;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ListingService
{
    /**
     * إنشاء ونشر إعلان — ذرّي: إعلان + صور + خصائص + حدث حالة أول.
     *
     * @param  array<string, mixed>  $validated  مخرجات ListingPayload::validate
     */
    public function create(User $owner, array $validated): Listing
    {
        return DB::transaction(function () use ($owner, $validated): Listing {
            $listing = Listing::query()->create([
                'user_id' => $owner->id,
                'category_id' => $validated['category_id'],
                'offer_type' => $validated['offer_type'],
                'title' => $validated['title'],
                'description' => $validated['description'],
                'price_cents' => $validated['price_cents'] ?? null,
                'currency' => $validated['currency'] ?? 'SYP',
                'price_type' => $validated['price_type'],
                'status' => Listing::STATUS_ACTIVE,
                'location_id' => $validated['location_id'],
                'lat' => $validated['lat'] ?? null,
                'lng' => $validated['lng'] ?? null,
                'shipping_available' => $validated['shipping_available'] ?? false,
                'carrier' => $validated['carrier'] ?? null,
                'published_at' => now(),
                'expires_at' => now()->addDays(Listing::EXPIRY_DAYS),
            ]);

            $this->syncImages($listing, $validated['media_ids'] ?? [], $validated['cover_media_id'] ?? null, $owner);
            $this->syncAttributes($listing, $validated['attributes'] ?? []);

            $listing->statusEvents()->create([
                'from_status' => null,
                'to_status' => Listing::STATUS_ACTIVE,
                'actor_id' => $owner->id,
                'actor_type' => ListingStatusEvent::ACTOR_OWNER,
                'created_at' => now(),
            ]);

            return $listing->fresh(['media', 'attributeValues.attribute', 'attributeValues.option', 'location', 'user.businessProfile', 'category']);
        });
    }

    /**
     * تحديث إعلان (مالك فقط، بعد فحص If-Match خارجيًا).
     */
    public function update(Listing $listing, User $owner, array $validated): Listing
    {
        return DB::transaction(function () use ($listing, $owner, $validated): Listing {
            $listing->fill([
                'category_id' => $validated['category_id'],
                'offer_type' => $validated['offer_type'],
                'title' => $validated['title'],
                'description' => $validated['description'],
                'price_cents' => $validated['price_cents'] ?? null,
                'currency' => $validated['currency'] ?? 'SYP',
                'price_type' => $validated['price_type'],
                'location_id' => $validated['location_id'],
                'lat' => $validated['lat'] ?? null,
                'lng' => $validated['lng'] ?? null,
                'shipping_available' => $validated['shipping_available'] ?? false,
                'carrier' => $validated['carrier'] ?? null,
            ]);
            $listing->version++;
            $listing->save();

            if (array_key_exists('media_ids', $validated)) {
                $listing->listingImages()->delete();
                $this->syncImages($listing, $validated['media_ids'] ?? [], $validated['cover_media_id'] ?? null, $owner);
            }

            $listing->attributeValues()->delete();
            $this->syncAttributes($listing, $validated['attributes'] ?? []);

            return $listing->fresh(['media', 'attributeValues.attribute', 'attributeValues.option', 'location', 'user.businessProfile', 'category']);
        });
    }

    /** @param  array<string, array{attribute:object,option:?object,value:mixed}>  $attrs */
    private function syncAttributes(Listing $listing, array $attrs): void
    {
        foreach ($attrs as $key => $a) {
            $row = new ListingAttributeValue([
                'listing_id' => $listing->id,
                'attribute_id' => $a['attribute']->id,
                'option_id' => $a['option']?->id,
            ]);
            $raw = $a['value'];
            if ($a['option'] === null && is_bool($raw)) {
                $row->value_bool = $raw;
            } elseif ($a['option'] === null && is_numeric($raw) && ! is_string($raw)) {
                $row->value_number = $raw;
            } elseif ($a['option'] === null && is_string($raw)) {
                $row->value_text = $raw;
            }
            $row->save();
        }
    }

    /**
     * @param  list<string>  $mediaIds
     *
     * @throws ValidationException
     */
    private function syncImages(Listing $listing, array $mediaIds, ?string $coverId, User $owner): void
    {
        if ($mediaIds === []) {
            return;
        }

        $media = Media::query()
            ->whereIn('id', $mediaIds)
            ->where('owner_id', $owner->id)
            ->where('status', '!=', Media::STATUS_REJECTED)
            ->get()
            ->keyBy('id');

        $invalid = array_diff($mediaIds, $media->keys()->all());
        if ($invalid !== []) {
            throw ValidationException::withMessages([
                'media_ids' => 'صور غير موجودة أو غير مملوكة لك: '.implode(', ', $invalid),
            ]);
        }

        $coverId = $coverId ?? $mediaIds[0];
        $position = 0;
        foreach ($mediaIds as $id) {
            ListingImage::query()->create([
                'listing_id' => $listing->id,
                'media_id' => $id,
                'position' => $position++,
                'is_cover' => $id === $coverId,
            ]);
        }
    }
}
