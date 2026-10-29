<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Controllers;

use App\Modules\Engagement\Models\Follow;
use App\Modules\Identity\Models\User;
use App\Modules\Listings\Http\Resources\ListingCardResource;
use App\Modules\Listings\Models\Listing;
use App\Modules\Listings\Support\CursorPage;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SellerController extends Controller
{
    public function show(Request $request, string $id): JsonResponse
    {
        $user = User::query()->with('businessProfile')->where('status', 'active')->find($id);
        if (! $user) {
            throw new ModelNotFoundException;
        }

        $viewer = $request->user('sanctum');
        $isFollowing = $viewer !== null
            && Follow::query()
                ->where('follower_id', $viewer->id)
                ->where('seller_id', $user->id)
                ->exists();

        return new JsonResponse(['data' => self::serializePublicUser($user, $isFollowing)]);
    }

    public function listings(Request $request, string $id): JsonResponse
    {
        if (! User::query()->where('status', 'active')->whereKey($id)->exists()) {
            throw new ModelNotFoundException;
        }

        $validated = $request->validate([
            'cursor' => ['sometimes', 'string', 'max:200'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:'.CursorPage::MAX_LIMIT],
        ]);

        $query = Listing::query()
            ->where('user_id', $id)
            ->whereIn('status', Listing::PUBLIC_STATUSES)
            ->where('moderation_state', Listing::MODERATION_CLEAN)
            ->with(['media', 'location', 'category', 'user']);

        $page = CursorPage::paginate($query, 'published_at', 'desc', $validated['cursor'] ?? null, (int) ($validated['limit'] ?? CursorPage::DEFAULT_LIMIT));

        return new JsonResponse([
            'data' => $page['data']->map(fn ($l) => (new ListingCardResource($l))->resolve())->all(),
            'next_cursor' => $page['next_cursor'],
        ]);
    }

    public function stats(string $id): JsonResponse
    {
        $user = User::query()->where('status', 'active')->find($id);
        if (! $user) {
            throw new ModelNotFoundException;
        }

        $active = Listing::query()
            ->where('user_id', $id)
            ->where('status', Listing::STATUS_ACTIVE)
            ->count();

        return new JsonResponse(['data' => [
            'member_since' => $user->created_at->toDateString(),
            'active_listings' => $active,
            'response_rate' => null, // يُحسب لاحقًا من بيانات المراسلة
        ]]);
    }

    /** @return array<string, mixed> */
    public static function serializePublicUser(User $user, bool $isFollowing = false): array
    {
        $bp = $user->relationLoaded('businessProfile') ? $user->businessProfile : null;

        return [
            'id' => $user->id,
            'display_name' => $user->display_name,
            'member_since' => $user->created_at->toDateString(),
            'location' => $bp?->city,
            'is_verified_business' => $bp?->status === 'verified',
            'business' => $bp?->status === 'verified' ? [
                'store_name' => $bp->store_name,
                'governorate' => $bp->governorate,
                'city' => $bp->city,
                'public_phone' => $bp->public_phone,
                'public_email' => $bp->public_email,
            ] : null,
            'is_following' => $isFollowing,
        ];
    }
}
