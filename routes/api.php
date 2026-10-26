<?php

use App\Modules\Identity\Http\Controllers\AuthController;
use App\Modules\Identity\Http\Controllers\BusinessController;
use App\Modules\Identity\Http\Controllers\ConsentController;
use App\Modules\Identity\Http\Controllers\DeviceController;
use App\Modules\Identity\Http\Controllers\EmailVerificationController;
use App\Modules\Identity\Http\Controllers\MeController;
use App\Modules\Identity\Http\Controllers\NotificationPreferenceController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;

if (! function_exists('readyCheck')) {
    function readyCheck(callable $fn): string
    {
        try {
            $fn();

            return 'ok';
        } catch (Throwable) {
            return 'fail';
        }
    }
}

Route::prefix('v1')->group(function (): void {
    // Liveness: لا يفحص التبعيات — يجب أن يرد حتى لو قاعدة البيانات ساقطة.
    Route::get('/health', fn () => new JsonResponse(['status' => 'ok']));

    // Readiness: يفحص ما يحتاجه التطبيق فعلًا قبل استقبال الترافيك.
    Route::get('/ready', function () {
        $checks = [
            'database' => readyCheck(fn () => DB::select('select 1')),
            'cache' => readyCheck(function () {
                Cache::put('ready:probe', '1', 10);
                if (Cache::get('ready:probe') !== '1') {
                    throw new RuntimeException('cache readback failed');
                }
            }),
            'queue' => readyCheck(fn () => Queue::size()),
        ];

        $healthy = ! in_array('fail', $checks, true);

        return new JsonResponse([
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => $checks,
        ], $healthy ? 200 : 503);
    });

    // ── Auth ──────────────────────────────────────────────────────────
    Route::prefix('auth')->group(function (): void {
        Route::post('register', [AuthController::class, 'register'])->middleware('throttle:5,1');
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1');
        Route::post('password/forgot', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,60');
        Route::post('password/reset', [AuthController::class, 'resetPassword'])->middleware('throttle:5,60');
        Route::post('email/verify', [EmailVerificationController::class, 'verify'])->middleware('throttle:10,1');

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('logout-all', [AuthController::class, 'logoutAll']);
            Route::post('email/resend', [EmailVerificationController::class, 'resend'])->middleware('throttle:3,60');
            Route::put('password', [AuthController::class, 'changePassword']);
        });
    });

    // ── Me ────────────────────────────────────────────────────────────
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('me', [MeController::class, 'show']);
        Route::patch('me', [MeController::class, 'update']);

        Route::get('me/notification-preferences', [NotificationPreferenceController::class, 'index']);
        Route::put('me/notification-preferences', [NotificationPreferenceController::class, 'update']);

        Route::get('me/consents', [ConsentController::class, 'index']);
        Route::post('me/consents', [ConsentController::class, 'store']);

        Route::post('me/devices', [DeviceController::class, 'store']);
        Route::delete('me/devices', [DeviceController::class, 'destroy']);

        Route::post('me/deletion', [MeController::class, 'requestDeletion']);
        Route::delete('me/deletion', [MeController::class, 'cancelDeletion']);

        // ── Merchant ─────────────────────────────────────────────────
        Route::get('me/business', [BusinessController::class, 'show']);
        Route::put('me/business', [BusinessController::class, 'update']);
        Route::get('me/business/status', [BusinessController::class, 'status']);
    });
});
