<?php

declare(strict_types=1);

namespace App\Modules\Safety\Http\Controllers;

use App\Modules\Engagement\Http\Controllers\SellerController;
use App\Modules\Identity\Models\User;
use App\Modules\Safety\Models\UserBlock;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpKernel\Exception\HttpException;

class BlockController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $blocked = User::query()
            ->join('user_blocks', 'user_blocks.blocked_id', '=', 'users.id')
            ->where('user_blocks.blocker_id', $request->user()->id)
            ->select('users.*')
            ->with('businessProfile')
            ->orderByDesc('user_blocks.created_at')
            ->get();

        return new JsonResponse([
            'data' => $blocked->map(fn ($u) => SellerController::serializePublicUser($u))->all(),
        ]);
    }

    public function store(Request $request, string $userId): JsonResponse
    {
        if ($userId === $request->user()->id) {
            throw new HttpException(422, 'لا يمكنك حظر نفسك.');
        }
        if (! User::query()->whereKey($userId)->exists()) {
            throw new ModelNotFoundException;
        }

        UserBlock::query()->insertOrIgnore([
            'blocker_id' => $request->user()->id,
            'blocked_id' => $userId,
            'created_at' => now(),
        ]);

        return new JsonResponse(null, 204);
    }

    public function destroy(Request $request, string $userId): JsonResponse
    {
        UserBlock::query()
            ->where('blocker_id', $request->user()->id)
            ->where('blocked_id', $userId)
            ->delete();

        return new JsonResponse(null, 204);
    }
}
