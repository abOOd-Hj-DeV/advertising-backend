<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Controllers;

use App\Modules\Engagement\Models\SavedSearch;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SavedSearchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $searches = SavedSearch::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get();

        return new JsonResponse(['data' => $searches->map(fn ($s) => $this->serialize($s))->all()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:60'],
            'query' => ['nullable', 'string', 'max:100'],
            'filters' => ['required', 'array'],
            'alerts_enabled' => ['sometimes', 'boolean'],
            'alert_frequency' => ['sometimes', 'in:instant,daily'],
        ]);

        if (SavedSearch::query()->where('user_id', $request->user()->id)->count() >= SavedSearch::MAX_PER_USER) {
            throw new HttpException(422, 'بلغت الحد الأقصى للأبحاث المحفوظة ('.SavedSearch::MAX_PER_USER.').');
        }

        $search = SavedSearch::query()->create([
            'user_id' => $request->user()->id,
            'name' => $data['name'] ?? null,
            'query' => $data['query'] ?? null,
            'filters' => $data['filters'],
            'alerts_enabled' => $data['alerts_enabled'] ?? true,
            'alert_frequency' => $data['alert_frequency'] ?? SavedSearch::FREQ_INSTANT,
        ]);

        return new JsonResponse(['data' => $this->serialize($search)], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $search = $this->findOwned($request, $id);

        $data = $request->validate([
            'name' => ['sometimes', 'nullable', 'string', 'max:60'],
            'alerts_enabled' => ['sometimes', 'boolean'],
            'alert_frequency' => ['sometimes', 'in:instant,daily'],
        ]);

        $search->update($data);

        return new JsonResponse(['data' => $this->serialize($search->fresh())]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->findOwned($request, $id)->delete();

        return new JsonResponse(null, 204);
    }

    private function findOwned(Request $request, string $id): SavedSearch
    {
        $search = SavedSearch::query()
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->first();
        if (! $search) {
            throw new ModelNotFoundException;
        }

        return $search;
    }

    /** @return array<string, mixed> */
    private function serialize(SavedSearch $s): array
    {
        return [
            'id' => $s->id,
            'name' => $s->name,
            'query' => $s->query,
            'filters' => $s->filters,
            'alerts_enabled' => $s->alerts_enabled,
            'alert_frequency' => $s->alert_frequency,
            'last_alerted_at' => $s->last_alerted_at?->toIso8601String(),
        ];
    }
}
