<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Http\Resources\UserResource;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\Notifications\VerifyEmailToken;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:190', 'unique:users,email'],
            'password' => ['required', PasswordRule::min(10)],
            'display_name' => ['required', 'string', 'min:2', 'max:40'],
            'account_type' => ['required', Rule::in([User::ACCOUNT_PRIVATE, User::ACCOUNT_COMMERCIAL])],
            'accept_terms_version' => ['required', 'string', 'max:20'],
            'device_name' => ['sometimes', 'string', 'max:80'],
        ]);

        $user = DB::transaction(function () use ($data, $request): User {
            $user = User::create([
                'email' => $data['email'],
                'password' => $data['password'],
                'display_name' => $data['display_name'],
                'account_type' => $data['account_type'],
                'locale' => 'ar',
            ]);

            $ipHash = hash('sha256', (string) $request->ip());
            $user->consents()->createMany([
                ['purpose' => 'terms', 'version' => $data['accept_terms_version'], 'granted' => true, 'ip_hash' => $ipHash],
                ['purpose' => 'privacy', 'version' => $data['accept_terms_version'], 'granted' => true, 'ip_hash' => $ipHash],
            ]);

            return $user;
        });

        VerifyEmailToken::issue($user);

        return new JsonResponse([
            'token' => $user->createToken($data['device_name'] ?? 'api')->plainTextToken,
            'user' => new UserResource($user),
        ], Response::HTTP_CREATED);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:80'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['بيانات الدخول غير صحيحة.'],
            ]);
        }

        // pending_deletion يبقى قادرًا على الدخول ليلغي الطلب ضمن فترة السماح.
        if ($user->status === User::STATUS_SUSPENDED) {
            throw new AccessDeniedHttpException('الحساب موقوف. راسل الدعم.');
        }

        $user->forceFill(['last_login_at' => now()])->save();

        return new JsonResponse([
            'token' => $user->createToken($data['device_name'])->plainTextToken,
            'user' => new UserResource($user),
        ]);
    }

    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    public function logoutAll(Request $request): Response
    {
        $request->user()->tokens()->delete();

        return response()->noContent();
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        Password::sendResetLink($request->only('email'));

        return new JsonResponse(['message' => 'إن كان البريد مسجلًا وصله رابط الاستعادة.'], Response::HTTP_ACCEPTED);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', PasswordRule::min(10)],
        ]);

        $status = Password::reset($data, function (User $user) use ($data) {
            $user->forceFill([
                'password' => $data['password'],
                'remember_token' => Str::random(60),
            ])->save();

            $user->tokens()->delete();
            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'token' => ['رابط الاستعادة غير صالح أو منتهي.'],
            ]);
        }

        return new JsonResponse(['message' => 'غيّرت كلمة المرور.']);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'different:current_password', PasswordRule::min(10)],
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['كلمة المرور الحالية غير صحيحة.'],
            ]);
        }

        $user->forceFill(['password' => $data['password']])->save();

        $user->tokens()
            ->where('id', '!=', $user->currentAccessToken()->id)
            ->delete();

        return new JsonResponse(['message' => 'غيّرت كلمة المرور وأُبطلت الجلسات الأخرى.']);
    }
}
