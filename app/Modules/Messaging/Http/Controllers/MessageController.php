<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Http\Controllers;

use App\Modules\Identity\Models\User;
use App\Modules\Messaging\Models\Conversation;
use App\Modules\Messaging\Models\Message;
use App\Modules\Safety\Models\UserBlock;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class MessageController extends Controller
{
    public function index(Request $request, string $id): JsonResponse
    {
        $conversation = $this->findParticipant($request, $id);
        $validated = $request->validate([
            'before' => ['sometimes', 'string', 'size:26'],
            'after' => ['sometimes', 'string', 'size:26'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $limit = min(100, max(1, (int) ($validated['limit'] ?? 30)));

        $query = $conversation->messages()
            ->where('moderation_state', '!=', 'deleted')
            ->orderBy('id');

        if (! empty($validated['before'])) {
            $query->where('id', '<', $validated['before'])->orderByDesc('id');
        } elseif (! empty($validated['after'])) {
            $query->where('id', '>', $validated['after']);
        } else {
            $query->orderByDesc('id'); // الأحدث أولًا بلا مؤشر
        }

        $items = $query->limit($limit + 1)->get();
        $next = null;
        if ($items->count() > $limit) {
            $items = $items->take($limit);
            $next = base64_encode(json_encode(['before' => $items->last()->id]));
        }
        // رتّب صاعدًا دائمًا في الرد
        $items = $items->sortBy('id')->values();

        return new JsonResponse([
            'data' => $items->map(fn ($m) => $this->serialize($m, $request->user()->id))->all(),
            'next_cursor' => $next,
        ]);
    }

    public function store(Request $request, string $id): JsonResponse
    {
        $conversation = $this->findParticipant($request, $id);
        $data = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:'.Message::BODY_MAX],
            'client_message_id' => ['required', 'string', 'size:26'],
        ]);

        $sender = $request->user();
        $otherId = $conversation->otherUserId($sender->id);
        if (UserBlock::blockedEitherWay($sender->id, $otherId)) {
            throw new HttpException(403, 'لا يمكن إرسال رسائل لهذا المستخدم.');
        }

        // تكرار client_message_id → أعد الرسالة المخزنة (منع التكرار)
        $existing = Message::query()
            ->where('sender_id', $sender->id)
            ->where('client_message_id', $data['client_message_id'])
            ->first();
        if ($existing) {
            return new JsonResponse(['data' => $this->serialize($existing, $sender->id)], 201);
        }

        $message = DB::transaction(fn () => self::append($conversation, $sender, $data['body'], $data['client_message_id']));

        return new JsonResponse(['data' => $this->serialize($message, $sender->id)], 201);
    }

    /** إدراج رسالة + تحديث last_message + عدّاد غير المقروء — داخل transaction */
    public static function append(Conversation $conversation, User $sender, string $body, string $clientMessageId): Message
    {
        $message = $conversation->messages()->create([
            'sender_id' => $sender->id,
            'body' => $body,
            'client_message_id' => $clientMessageId,
            'moderation_state' => 'clean',
        ]);

        $conversation->update([
            'last_message_id' => $message->id,
            'last_message_at' => $message->created_at,
        ]);

        $conversation->participants()
            ->where('user_id', '!=', $sender->id)
            ->increment('unread_count');

        // إعادة فتح المحادثة للطرف الآخر إن كان حذفها
        $conversation->participants()
            ->where('user_id', '!=', $sender->id)
            ->whereNotNull('deleted_at')
            ->update(['deleted_at' => null]);

        return $message;
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
    private function serialize(Message $m, string $meId): array
    {
        return [
            'id' => $m->id,
            'client_message_id' => $m->client_message_id,
            'sender_id' => $m->sender_id,
            'body' => $m->body,
            'created_at' => $m->created_at->toIso8601String(),
            'is_mine' => $m->sender_id === $meId,
        ];
    }
}
