<?php

declare(strict_types=1);

namespace App\Modules\Listings\Http\Controllers;

use App\Modules\Listings\Http\Requests\ListingPayload;
use App\Modules\Listings\Http\Resources\ListingResource;
use App\Modules\Listings\Models\Listing;
use App\Modules\Listings\Models\ListingDraft;
use App\Modules\Listings\Services\ListingService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DraftController extends Controller
{
    public function __construct(private readonly ListingService $service) {}

    public function index(Request $request): JsonResponse
    {
        $drafts = ListingDraft::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('updated_at')
            ->get();

        return new JsonResponse(['data' => $drafts->map(fn ($d) => $this->serialize($d))->all()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'listing_id' => ['nullable', 'string', 'size:26'],
            'payload' => ['required', 'array'],
            'media_ids' => ['nullable', 'array'],
            'media_ids.*' => ['string', 'size:26'],
        ]);

        if (! empty($data['listing_id'])) {
            $listing = Listing::query()->find($data['listing_id']);
            if (! $listing || $listing->user_id !== $request->user()->id) {
                throw new ModelNotFoundException;
            }
        }

        $count = ListingDraft::query()->where('user_id', $request->user()->id)->count();
        if ($count >= ListingDraft::MAX_PER_USER && empty($data['listing_id'])) {
            throw new HttpException(422, 'بلغت الحد الأقصى للمسودات ('.ListingDraft::MAX_PER_USER.').');
        }

        $draft = ListingDraft::query()->updateOrCreate(
            ['user_id' => $request->user()->id, 'listing_id' => $data['listing_id'] ?? null],
            ['payload' => $data['payload'], 'media_ids' => $data['media_ids'] ?? null]
        );
        if (! $draft->wasRecentlyCreated) {
            $draft->increment('version');
            $draft->touch();
        }

        return new JsonResponse(['data' => $this->serialize($draft->fresh())], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        return new JsonResponse(['data' => $this->serialize($this->findOwned($request, $id))]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $draft = $this->findOwned($request, $id);
        $this->checkIfMatch($request, $draft);

        $data = $request->validate([
            'payload' => ['required', 'array'],
            'media_ids' => ['nullable', 'array'],
            'media_ids.*' => ['string', 'size:26'],
        ]);

        $draft->payload = $data['payload'];
        $draft->media_ids = $data['media_ids'] ?? $draft->media_ids;
        $draft->version++;
        $draft->save();

        return new JsonResponse(['data' => $this->serialize($draft)]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->findOwned($request, $id)->delete();

        return new JsonResponse(null, 204);
    }

    public function publish(Request $request, string $id): JsonResponse
    {
        $draft = $this->findOwned($request, $id);

        if (! $request->user()->hasVerifiedEmail()) {
            throw new HttpException(403, 'وثّق بريدك الإلكتروني قبل نشر إعلان.');
        }

        $input = $draft->payload;
        if ($draft->media_ids) {
            $input['media_ids'] = $draft->media_ids;
        }
        $validated = ListingPayload::validate($input);

        $listing = $draft->listing_id
            ? $this->service->update(Listing::query()->findOrFail($draft->listing_id), $request->user(), $validated)
            : $this->service->create($request->user(), $validated);

        $draft->delete();

        return (new ListingResource($listing))->response()->setStatusCode(201);
    }

    private function findOwned(Request $request, string $id): ListingDraft
    {
        $draft = ListingDraft::query()
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();
        if (! $draft) {
            throw new ModelNotFoundException;
        }

        return $draft;
    }

    private function checkIfMatch(Request $request, ListingDraft $draft): void
    {
        $ifMatch = $request->header('If-Match');
        if ($ifMatch === null || (int) $ifMatch !== $draft->version) {
            throw new HttpException(412, 'إصدار قديم — أعد تحميل المسودة وحاول مجددًا.');
        }
    }

    /** @return array<string, mixed> */
    private function serialize(ListingDraft $d): array
    {
        return [
            'id' => $d->id,
            'listing_id' => $d->listing_id,
            'payload' => $d->payload,
            'media_ids' => $d->media_ids,
            'version' => $d->version,
            'updated_at' => $d->updated_at->toIso8601String(),
        ];
    }
}
