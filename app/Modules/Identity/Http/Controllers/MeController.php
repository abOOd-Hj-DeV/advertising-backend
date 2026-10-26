<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Http\Resources\UserResource;
use App\Modules\Identity\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MeController extends Controller
{
    public function show(Request $request): UserResource
    {
        return new UserResource($request->user()->loadMissing('businessProfile'));
    }

    public function update(Request $request): UserResource
    {
        $data = $request->validate([
            'display_name' => ['sometimes', 'string', 'min:2', 'max:40'],
            'default_location_id' => ['nullable', 'string', 'size:26'],
            'locale' => ['sometimes', Rule::in(['ar'])],
        ]);

        if (isset($data['default_location_id']) && ! Str::isUlid($data['default_location_id'])) {
            throw ValidationException::withMessages(['default_location_id' => ['معرّف موقع غير صالح.']]);
        }

        $request->user()->update($data);

        return new UserResource($request->user()->fresh());
    }

    public function requestDeletion(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->status === User::STATUS_PENDING_DELETION) {
            return new JsonResponse(['message' => 'الطلب موجود مسبقًا.'], Response::HTTP_ACCEPTED);
        }

        DB::transaction(function () use ($user): void {
            $user->deletionRequests()->create([
                'requested_at' => now(),
                'scheduled_for' => now()->addDays(30),
            ]);
            $user->forceFill(['status' => User::STATUS_PENDING_DELETION])->save();
        });

        $user->tokens()->delete();

        return new JsonResponse([
            'message' => 'حُجز الحذف بعد 30 يومًا. سجّل الدخول لإلغائه ضمن المهلة.',
        ], Response::HTTP_ACCEPTED);
    }

    public function cancelDeletion(Request $request): Response
    {
        $user = $request->user();

        if ($user->status !== User::STATUS_PENDING_DELETION) {
            throw ValidationException::withMessages([
                'deletion' => ['لا يوجد طلب حذف نشط.'],
            ]);
        }

        DB::transaction(function () use ($user): void {
            $user->deletionRequests()
                ->whereNull('cancelled_at')
                ->whereNull('completed_at')
                ->update(['cancelled_at' => now()]);
            $user->forceFill(['status' => User::STATUS_ACTIVE])->save();
        });

        return response()->noContent();
    }
}
