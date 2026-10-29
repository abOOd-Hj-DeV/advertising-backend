<?php

declare(strict_types=1);

namespace App\Modules\Listings\Http\Controllers;

use App\Modules\Listings\Http\Resources\ListingCardResource;
use App\Modules\Listings\Models\Listing;
use App\Modules\Listings\Support\CursorPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ListingSearchController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['sometimes', 'string', 'max:100'],
            'category' => ['sometimes', 'string', 'size:26'],
            'attributes' => ['sometimes', 'array'],
            'attributes.*' => ['string', 'max:80'],
            'price_min' => ['sometimes', 'integer', 'min:0'],
            'price_max' => ['sometimes', 'integer', 'min:0'],
            'currency' => ['sometimes', 'in:SYP,USD'],
            'governorate' => ['sometimes', 'string', 'max:60'],
            'city' => ['sometimes', 'string', 'max:80'],
            'shipping' => ['sometimes', 'in:any,only'],
            'carrier' => ['sometimes', 'string', 'max:60'],
            'seller_type' => ['sometimes', 'in:any,private,commercial'],
            'offer_type' => ['sometimes', 'in:offer,wanted,any'],
            'sort' => ['sometimes', 'in:recommended,newest,price_asc,price_desc'],
            'cursor' => ['sometimes', 'string', 'max:200'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:'.CursorPage::MAX_LIMIT],
        ]);

        $query = Listing::query()
            ->where('status', Listing::STATUS_ACTIVE)
            ->where('moderation_state', Listing::MODERATION_CLEAN)
            ->with(['media', 'location']);

        if (! empty($validated['q'])) {
            $q = $validated['q'];
            $query->where(function ($w) use ($q): void {
                $w->where('title', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            });
        }

        if (! empty($validated['category'])) {
            $catId = $validated['category'];
            $query->where(function ($w) use ($catId): void {
                $w->where('category_id', $catId)
                    ->orWhereIn('category_id', function ($sub) use ($catId): void {
                        $sub->select('id')->from('categories')->where('parent_id', $catId);
                    });
            });
        }

        foreach (($validated['attributes'] ?? []) as $key => $value) {
            $query->whereHas('attributeValues', function ($av) use ($key, $value): void {
                $av->whereHas('attribute', fn ($a) => $a->where('key', $key))
                    ->where(function ($w) use ($value): void {
                        $w->whereHas('option', fn ($o) => $o->where('value', $value)->orWhere('label_ar', $value))
                            ->orWhere('value_text', $value)
                            ->orWhere('value_number', is_numeric($value) ? $value : -999999999);
                    });
            });
        }

        if (isset($validated['price_min'])) {
            $query->where('price_cents', '>=', $validated['price_min']);
        }
        if (isset($validated['price_max'])) {
            $query->where('price_cents', '<=', $validated['price_max']);
        }
        if (! empty($validated['currency'])) {
            $query->where('currency', $validated['currency']);
        }
        if (! empty($validated['governorate']) || ! empty($validated['city'])) {
            $query->whereHas('location', function ($l) use ($validated): void {
                if (! empty($validated['governorate'])) {
                    $l->where('governorate', $validated['governorate']);
                }
                if (! empty($validated['city'])) {
                    $l->where('city', $validated['city']);
                }
            });
        }
        if (($validated['shipping'] ?? 'any') === 'only') {
            $query->where('shipping_available', true);
        }
        if (! empty($validated['carrier'])) {
            $query->where('carrier', $validated['carrier']);
        }
        if (($validated['offer_type'] ?? 'any') !== 'any') {
            $query->where('offer_type', $validated['offer_type']);
        }
        if (($validated['seller_type'] ?? 'any') !== 'any') {
            $query->whereHas('user', fn ($u) => $u->where('account_type', $validated['seller_type']));
        }

        [$column, $dir] = match ($validated['sort'] ?? 'recommended') {
            'price_asc' => ['price_cents', 'asc'],
            'price_desc' => ['price_cents', 'desc'],
            default => ['published_at', 'desc'],
        };
        // price sorts على NULL: ضعها آخرًا
        if ($column === 'price_cents') {
            $query->orderByRaw('price_cents IS NULL');
        }

        $page = CursorPage::paginate(
            $query, $column, $dir,
            $validated['cursor'] ?? null,
            (int) ($validated['limit'] ?? CursorPage::DEFAULT_LIMIT)
        );

        return new JsonResponse([
            'data' => $page['data']->map(fn ($l) => (new ListingCardResource($l))->resolve())->all(),
            'next_cursor' => $page['next_cursor'],
        ]);
    }
}
