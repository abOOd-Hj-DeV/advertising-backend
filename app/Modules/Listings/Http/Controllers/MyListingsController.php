<?php

declare(strict_types=1);

namespace App\Modules\Listings\Http\Controllers;

use App\Modules\Listings\Http\Resources\ListingCardResource;
use App\Modules\Listings\Models\Listing;
use App\Modules\Listings\Support\CursorPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class MyListingsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'in:active,reserved,sold,expired,deleted,all'],
            'cursor' => ['sometimes', 'string', 'max:200'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:'.CursorPage::MAX_LIMIT],
        ]);

        $status = $validated['status'] ?? 'all';

        $query = Listing::query()
            ->where('user_id', $request->user()->id)
            ->with(['media', 'location'])
            ->when($status !== 'all', function ($q) use ($status): void {
                if ($status === 'deleted') {
                    $q->onlyTrashed();
                } else {
                    $q->where('status', $status);
                }
            })
            ->when($status !== 'deleted', fn ($q) => $q->where('status', '!=', Listing::STATUS_DELETED));

        $page = CursorPage::paginate($query, 'published_at', 'desc', $validated['cursor'] ?? null, (int) ($validated['limit'] ?? CursorPage::DEFAULT_LIMIT));

        return new JsonResponse([
            'data' => $page['data']->map(fn ($l) => (new ListingCardResource($l))->resolve())->all(),
            'next_cursor' => $page['next_cursor'],
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $counts = Listing::query()
            ->where('user_id', $userId)
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status')
            ->all();

        $counts['deleted'] = Listing::query()->onlyTrashed()->where('user_id', $userId)->count();

        return new JsonResponse([
            'counts' => $counts,
            'total_views' => (int) Listing::query()->where('user_id', $userId)->sum('views_count'),
        ]);
    }
}
