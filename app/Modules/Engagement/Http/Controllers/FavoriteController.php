<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Controllers;

use App\Modules\Engagement\Models\Favorite;
use App\Modules\Listings\Http\Resources\ListingCardResource;
use App\Modules\Listings\Models\Listing;
use App\Modules\Listings\Support\CursorPage;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class FavoriteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cursor' => ['sometimes', 'string', 'max:200'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:'.CursorPage::MAX_LIMIT],
        ]);

        $query = Listing::query()
            ->join('favorites', 'favorites.listing_id', '=', 'listings.id')
            ->where('favorites.user_id', $request->user()->id)
            ->select('listings.*')
            ->with(['media', 'location', 'category', 'user']);

        $page = CursorPage::paginate(
            $query, 'favorites.created_at', 'desc',
            $validated['cursor'] ?? null,
            (int) ($validated['limit'] ?? CursorPage::DEFAULT_LIMIT)
        );

        return new JsonResponse([
            'data' => $page['data']->map(fn ($l) => (new ListingCardResource($l))->resolve())->all(),
            'next_cursor' => $page['next_cursor'],
        ]);
    }

    public function store(Request $request, string $listingId): JsonResponse
    {
        $listing = Listing::query()->where('status', '!=', Listing::STATUS_DELETED)->find($listingId);
        if (! $listing || $listing->trashed()) {
            throw new ModelNotFoundException;
        }

        DB::transaction(function () use ($request, $listing): void {
            $created = Favorite::query()->insertOrIgnore([
                'user_id' => $request->user()->id,
                'listing_id' => $listing->id,
                'created_at' => now(),
            ]);
            if ($created) {
                Listing::query()->whereKey($listing->id)->increment('favorites_count');
            }
        });

        return new JsonResponse(null, 204);
    }

    public function destroy(Request $request, string $listingId): JsonResponse
    {
        DB::transaction(function () use ($request, $listingId): void {
            $deleted = Favorite::query()
                ->where('user_id', $request->user()->id)
                ->where('listing_id', $listingId)
                ->delete();
            if ($deleted) {
                Listing::query()->whereKey($listingId)->where('favorites_count', '>', 0)->decrement('favorites_count');
            }
        });

        return new JsonResponse(null, 204);
    }
}
