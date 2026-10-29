<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Controllers;

use App\Modules\Engagement\Models\RecentlyViewed;
use App\Modules\Listings\Http\Resources\ListingCardResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class RecentlyViewedController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = RecentlyViewed::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('viewed_at')
            ->limit(RecentlyViewed::MAX_PER_USER)
            ->with('listing.media', 'listing.location')
            ->get()
            ->filter(fn ($rv) => $rv->listing !== null && ! $rv->listing->trashed());

        return new JsonResponse([
            'data' => $items->map(fn ($rv) => (new ListingCardResource($rv->listing))->resolve())->values()->all(),
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        RecentlyViewed::query()->where('user_id', $request->user()->id)->delete();

        return new JsonResponse(null, 204);
    }

    /** تسجيل مشاهدة — upsert + الاحتفاظ بآخر MAX_PER_USER */
    public static function record(string $userId, string $listingId): void
    {
        DB::transaction(function () use ($userId, $listingId): void {
            RecentlyViewed::query()->updateOrInsert(
                ['user_id' => $userId, 'listing_id' => $listingId],
                ['viewed_at' => now()]
            );

            $keep = RecentlyViewed::query()
                ->where('user_id', $userId)
                ->orderByDesc('viewed_at')
                ->limit(RecentlyViewed::MAX_PER_USER)
                ->pluck('listing_id');

            RecentlyViewed::query()
                ->where('user_id', $userId)
                ->whereNotIn('listing_id', $keep)
                ->delete();
        });
    }
}
