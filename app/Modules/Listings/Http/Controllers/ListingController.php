<?php

declare(strict_types=1);

namespace App\Modules\Listings\Http\Controllers;

use App\Modules\Engagement\Http\Controllers\RecentlyViewedController;
use App\Modules\Engagement\Models\Favorite;
use App\Modules\Listings\Http\Requests\ListingPayload;
use App\Modules\Listings\Http\Resources\ListingCardResource;
use App\Modules\Listings\Http\Resources\ListingResource;
use App\Modules\Listings\Models\Listing;
use App\Modules\Listings\Models\ListingStatusEvent;
use App\Modules\Listings\Services\ListingService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ListingController extends Controller
{
    public function __construct(private readonly ListingService $service) {}

    public function show(Request $request, string $id): JsonResponse
    {
        $listing = Listing::query()
            ->with(['media', 'attributeValues.attribute', 'attributeValues.option', 'location', 'user.businessProfile', 'category'])
            ->withTrashed()
            ->find($id);

        if (! $listing) {
            throw new ModelNotFoundException;
        }

        $isOwner = $request->user() && $listing->isOwnedBy($request->user());

        if ($listing->trashed() || $listing->status === Listing::STATUS_DELETED) {
            if (! $isOwner) {
                // «الإعلان غير متوفر»
                throw new HttpException(410, 'الإعلان غير متوفر.');
            }
        } elseif (! $listing->isPubliclyVisible() && ! $isOwner) {
            throw new HttpException(410, 'الإعلان غير متوفر.');
        }

        if (! $isOwner && in_array($listing->status, Listing::PUBLIC_STATUSES, true)) {
            Listing::query()->whereKey($listing->id)->increment('views_count');
            $listing->views_count++;
        }

        if ($request->user()) {
            $listing->setAttribute('is_favorite', Favorite::query()
                ->where('user_id', $request->user()->id)
                ->where('listing_id', $listing->id)
                ->exists());
            RecentlyViewedController::record($request->user()->id, $listing->id);
        }

        return (new ListingResource($listing))->response();
    }

    public function store(Request $request): JsonResponse
    {
        $this->requireVerifiedEmail($request);
        $validated = ListingPayload::validate($request->all());

        $listing = $this->service->create($request->user(), $validated);

        return (new ListingResource($listing))->response()->setStatusCode(201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $listing = $this->findOwned($request, $id);
        $this->checkIfMatch($request, $listing);

        if (! in_array($listing->status, [Listing::STATUS_ACTIVE, Listing::STATUS_RESERVED, Listing::STATUS_EXPIRED], true)) {
            throw new ConflictHttpException('لا يمكن تعديل إعلان بهذه الحالة.');
        }

        $validated = ListingPayload::validate($request->all());
        $listing = $this->service->update($listing, $request->user(), $validated);

        return (new ListingResource($listing))->response();
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $listing = $this->findOwned($request, $id);
        if (in_array($listing->status, [Listing::STATUS_DELETED], true) || $listing->trashed()) {
            return new JsonResponse(null, 204);
        }

        DB::transaction(function () use ($listing, $request): void {
            $listing->status = Listing::STATUS_DELETED;
            $listing->version++;
            $listing->save();
            $listing->delete();
            $listing->statusEvents()->create([
                'from_status' => $listing->getOriginal('status'),
                'to_status' => Listing::STATUS_DELETED,
                'actor_id' => $request->user()->id,
                'actor_type' => ListingStatusEvent::ACTOR_OWNER,
                'created_at' => now(),
            ]);
        });

        return new JsonResponse(null, 204);
    }

    /** reserve | unreserve | sell | reactivate */
    public function transition(Request $request, string $id, string $action): JsonResponse
    {
        $listing = $this->findOwned($request, $id);
        $map = Listing::allowedOwnerTransitions()[$action] ?? null;

        if (! $map || ! isset($map[$listing->status])) {
            throw new ConflictHttpException("لا يمكن تنفيذ «{$action}» على إعلان بحالة «{$listing->status}».");
        }

        DB::transaction(function () use ($listing, $request, $map, $action): void {
            $to = $map[$listing->status];
            $listing->transitionTo($to, $request->user(), ListingStatusEvent::ACTOR_OWNER);

            if ($action === 'reactivate') {
                $listing->published_at = now();
                $listing->expires_at = now()->addDays(Listing::EXPIRY_DAYS);
                $listing->reserved_at = null;
                $listing->sold_at = null;
                $listing->save();
            }
        });

        return (new ListingResource($listing->fresh(['media', 'attributeValues.attribute', 'attributeValues.option', 'location', 'user.businessProfile', 'category'])))->response();
    }

    public function extend(Request $request, string $id): JsonResponse
    {
        $listing = $this->findOwned($request, $id);
        if ($listing->status !== Listing::STATUS_ACTIVE) {
            throw new ConflictHttpException('التمديد متاح للإعلانات النشطة فقط.');
        }

        $listing->expires_at = ($listing->expires_at ?? now())->gt(now())
            ? $listing->expires_at->addDays(Listing::EXPIRY_DAYS)
            : now()->addDays(Listing::EXPIRY_DAYS);
        $listing->version++;
        $listing->save();

        return (new ListingResource($listing->fresh(['media', 'attributeValues.attribute', 'attributeValues.option', 'location', 'user.businessProfile', 'category'])))->response();
    }

    public function similar(string $id): JsonResponse
    {
        $listing = Listing::query()->findOrFail($id);

        $similar = Listing::query()
            ->where('category_id', $listing->category_id)
            ->where('id', '!=', $listing->id)
            ->whereIn('status', [Listing::STATUS_ACTIVE])
            ->where('moderation_state', Listing::MODERATION_CLEAN)
            ->with(['media', 'location'])
            ->orderByDesc('published_at')
            ->limit(10)
            ->get();

        return new JsonResponse(['data' => $similar->map(fn ($l) => (new ListingCardResource($l))->resolve())->all()]);
    }

    private function findOwned(Request $request, string $id): Listing
    {
        $listing = Listing::query()->find($id);
        if (! $listing) {
            throw new ModelNotFoundException;
        }
        if (! $request->user() || ! $listing->isOwnedBy($request->user())) {
            throw new AccessDeniedHttpException;
        }

        return $listing;
    }

    private function requireVerifiedEmail(Request $request): void
    {
        if (! $request->user()->hasVerifiedEmail()) {
            throw new HttpException(403, 'وثّق بريدك الإلكتروني قبل نشر إعلان.');
        }
    }

    private function checkIfMatch(Request $request, Listing $listing): void
    {
        $ifMatch = $request->header('If-Match');
        if ($ifMatch === null || (int) $ifMatch !== $listing->version) {
            throw new HttpException(412, 'إصدار قديم — أعد تحميل الإعلان وحاول مجددًا.');
        }
    }
}
