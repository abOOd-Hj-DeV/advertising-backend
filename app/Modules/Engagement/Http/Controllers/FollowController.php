<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Controllers;

use App\Modules\Engagement\Models\Follow;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpKernel\Exception\HttpException;

class FollowController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $sellers = User::query()
            ->join('follows', 'follows.seller_id', '=', 'users.id')
            ->where('follows.follower_id', $request->user()->id)
            ->select('users.*')
            ->with('businessProfile')
            ->orderByDesc('follows.created_at')
            ->get();

        return new JsonResponse([
            'data' => $sellers->map(fn ($u) => SellerController::serializePublicUser($u, true))->all(),
        ]);
    }

    public function store(Request $request, string $userId): JsonResponse
    {
        if ($userId === $request->user()->id) {
            throw new HttpException(422, 'لا يمكنك متابعة نفسك.');
        }

        $seller = User::query()->where('status', 'active')->find($userId);
        if (! $seller) {
            throw new ModelNotFoundException;
        }

        Follow::query()->insertOrIgnore([
            'follower_id' => $request->user()->id,
            'seller_id' => $seller->id,
            'created_at' => now(),
        ]);

        return new JsonResponse(null, 204);
    }

    public function destroy(Request $request, string $userId): JsonResponse
    {
        Follow::query()
            ->where('follower_id', $request->user()->id)
            ->where('seller_id', $userId)
            ->delete();

        return new JsonResponse(null, 204);
    }
}
