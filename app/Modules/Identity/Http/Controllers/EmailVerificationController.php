<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Models\User;
use App\Modules\Identity\Notifications\VerifyEmailToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EmailVerificationController extends Controller
{
    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:120']]);

        [$userId, $random] = array_pad(explode('.', $data['token'], 2), 2, '');

        $user = Str::isUlid($userId) ? User::find($userId) : null;
        $expected = $user ? Cache::get(VerifyEmailToken::cacheKey($user->id)) : null;

        if (! $user || ! is_string($expected) || ! hash_equals($expected, hash('sha256', $data['token']))) {
            throw ValidationException::withMessages([
                'token' => ['رابط التأكيد غير صالح أو منتهي. اطلب رابطًا جديدًا.'],
            ]);
        }

        $user->forceFill(['email_verified_at' => now()])->save();
        Cache::forget(VerifyEmailToken::cacheKey($user->id));

        return new JsonResponse(['verified' => true]);
    }

    public function resend(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasVerifiedEmail()) {
            VerifyEmailToken::issue($user);
        }

        return new JsonResponse(['message' => 'إن لم يكن بريدك مؤكدًا أُرسل رابط جديد.'], Response::HTTP_ACCEPTED);
    }
}
