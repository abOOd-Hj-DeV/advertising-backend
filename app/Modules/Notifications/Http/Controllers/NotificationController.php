<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $limit = min(50, max(1, (int) $request->query('limit', 20)));
        $cursor = $request->query('cursor');

        $query = $request->user()->notifications()
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if (is_string($cursor) && $cursor !== '') {
            $decoded = json_decode(base64_decode($cursor, true) ?: '', true);
            if (is_array($decoded) && isset($decoded[0])) {
                $query->where('created_at', '<', $decoded[0]);
            }
        }

        $items = $query->limit($limit + 1)->get();
        $next = null;
        if ($items->count() > $limit) {
            $items = $items->take($limit);
            $next = base64_encode(json_encode([$items->last()->created_at->toIso8601String()]));
        }

        return new JsonResponse([
            'data' => $items->map(fn ($n) => [
                'id' => $n->id,
                'type' => $n->type,
                'data' => $n->data,
                'read_at' => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at->toIso8601String(),
            ])->values()->all(),
            'next_cursor' => $next,
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return new JsonResponse(['unread' => $request->user()->unreadNotifications()->count()]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $request->user()->notifications()->whereKey($id)->update(['read_at' => now()]);

        return new JsonResponse(null, 204);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return new JsonResponse(null, 204);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $request->user()->notifications()->whereKey($id)->delete();

        return new JsonResponse(null, 204);
    }
}
