<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Http\Controllers;

use App\Modules\Engagement\Http\Controllers\SellerController;
use App\Modules\Listings\Http\Resources\ListingCardResource;
use App\Modules\Listings\Models\Listing;
use App\Modules\Messaging\Models\Conversation;
use App\Modules\Messaging\Models\ConversationParticipant;
use App\Modules\Messaging\Models\Message;
use App\Modules\Safety\Models\UserBlock;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ConversationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'archived' => ['sometimes', 'boolean'],
            'cursor' => ['sometimes', 'string', 'max:200'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);
        $archived = (bool) ($validated['archived'] ?? false);
        $limit = min(50, max(1, (int) ($validated['limit'] ?? 20)));

        $query = Conversation::query()
            ->join('conversation_participants as cp', function ($j) use ($request): void {
                $j->on('cp.conversation_id', '=', 'conversations.id')
                    ->where('cp.user_id', '=', $request->user()->id);
            })
            ->whereNull('cp.deleted_at')
            ->where($archived ? 'cp.archived_at' : 'cp.archived_at', $archived ? '!=' : '=', null)
            ->when(! $archived, fn ($q) => $q->whereNull('cp.archived_at'))
            ->when($archived, fn ($q) => $q->whereNotNull('cp.archived_at'))
            ->select('conversations.*', 'cp.unread_count as my_unread', 'cp.archived_at as my_archived_at')
            ->with(['listing.media', 'listing.location', 'seller.businessProfile', 'buyer'])
            ->orderByDesc('conversations.last_message_at')
            ->orderByDesc('conversations.id');

        if (! empty($validated['cursor'])) {
            $decoded = json_decode(base64_decode($validated['cursor'], true) ?: '', true);
            if (is_array($decoded) && isset($decoded[0])) {
                $query->where('conversations.last_message_at', '<', $decoded[0]);
            }
        }

        $items = $query->limit($limit + 1)->get();
        $next = null;
        if ($items->count() > $limit) {
            $items = $items->take($limit);
            $next = base64_encode(json_encode([(string) $items->last()->last_message_at]));
        }

        return new JsonResponse([
            'data' => $items->map(fn ($c) => $this->serialize($c, $request->user()->id))->values()->all(),
            'next_cursor' => $next,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'listing_id' => ['required', 'string', 'size:26'],
            'first_message' => ['nullable', 'string', 'max:'.Message::BODY_MAX],
        ]);

        $buyer = $request->user();
        $listing = Listing::query()->find($data['listing_id']);
        if (! $listing || $listing->trashed() || ! $listing->isPubliclyVisible()) {
            throw new ModelNotFoundException;
        }
        if ($listing->isOwnedBy($buyer)) {
            throw new HttpException(403, 'لا يمكنك مراسلة نفسك على إعلانك.');
        }
        if (UserBlock::blockedEitherWay($buyer->id, $listing->user_id)) {
            throw new HttpException(403, 'لا يمكن بدء محادثة مع هذا المستخدم.');
        }

        $conversation = DB::transaction(function () use ($listing, $buyer, $data): Conversation {
            $existing = Conversation::query()
                ->where('listing_id', $listing->id)
                ->where('buyer_id', $buyer->id)
                ->first();
            if ($existing) {
                // أعد فتحها إن حذفها المشتري سابقًا
                $existing->participants()
                    ->where('user_id', $buyer->id)
                    ->update(['deleted_at' => null, 'archived_at' => null]);

                if (! empty($data['first_message'])) {
                    MessageController::append($existing, $buyer, $data['first_message'], (string) Str::ulid());
                }

                return $existing;
            }

            $conversation = Conversation::query()->create([
                'listing_id' => $listing->id,
                'seller_id' => $listing->user_id,
                'buyer_id' => $buyer->id,
                'listing_title_snapshot' => $listing->title,
            ]);
            foreach ([$listing->user_id, $buyer->id] as $uid) {
                ConversationParticipant::query()->create([
                    'conversation_id' => $conversation->id,
                    'user_id' => $uid,
                ]);
            }

            if (! empty($data['first_message'])) {
                MessageController::append($conversation, $buyer, $data['first_message'], (string) Str::ulid());
            }

            return $conversation;
        });

        return new JsonResponse(['data' => $this->serialize(
            $conversation->fresh(['listing.media', 'listing.location', 'seller.businessProfile', 'buyer']),
            $buyer->id
        )], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $conversation = $this->findParticipant($request, $id)
            ->loadMissing(['listing.media', 'listing.location', 'seller.businessProfile', 'buyer']);

        return new JsonResponse(['data' => $this->serialize($conversation, $request->user()->id)]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $conversation = $this->findParticipant($request, $id);
        $data = $request->validate(['archived' => ['required', 'boolean']]);

        $conversation->participants()
            ->where('user_id', $request->user()->id)
            ->update(['archived_at' => $data['archived'] ? now() : null]);

        return new JsonResponse(['data' => ['archived' => $data['archived']]]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $conversation = $this->findParticipant($request, $id);
        $conversation->participants()
            ->where('user_id', $request->user()->id)
            ->update(['deleted_at' => now()]);

        return new JsonResponse(null, 204);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $conversation = $this->findParticipant($request, $id);
        $data = $request->validate(['last_read_message_id' => ['required', 'string', 'size:26']]);

        $conversation->participants()
            ->where('user_id', $request->user()->id)
            ->update(['last_read_message_id' => $data['last_read_message_id'], 'unread_count' => 0]);

        return new JsonResponse(null, 204);
    }

    private function findParticipant(Request $request, string $id): Conversation
    {
        $conversation = Conversation::query()->find($id);
        if (! $conversation || ! $conversation->isParticipant($request->user())) {
            throw new ModelNotFoundException;
        }

        return $conversation;
    }

    /** @return array<string, mixed> */
    private function serialize(Conversation $c, string $meId): array
    {
        $other = $c->seller_id === $meId ? $c->buyer : $c->seller;
        $last = $c->last_message_id
            ? Message::query()->find($c->last_message_id)
            : null;

        $me = $c->participants()->where('user_id', $meId)->first();

        return [
            'id' => $c->id,
            'listing' => (new ListingCardResource($c->listing))->resolve(),
            'participant' => SellerController::serializePublicUser($other),
            'last_message' => $last ? [
                'id' => $last->id,
                'body' => $last->body,
                'sender_id' => $last->sender_id,
                'created_at' => $last->created_at->toIso8601String(),
                'is_mine' => $last->sender_id === $meId,
            ] : null,
            'unread_count' => $me->unread_count ?? $c->my_unread ?? 0,
            'archived' => ($me->archived_at ?? $c->my_archived_at ?? null) !== null,
            'updated_at' => ($c->last_message_at ?? $c->updated_at)->toIso8601String(),
        ];
    }
}
