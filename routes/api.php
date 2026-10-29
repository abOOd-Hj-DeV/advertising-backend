<?php

use App\Modules\Catalog\Http\Controllers\CategoryController;
use App\Modules\Catalog\Http\Controllers\LocationController;
use App\Modules\Engagement\Http\Controllers\FavoriteController;
use App\Modules\Engagement\Http\Controllers\FollowController;
use App\Modules\Engagement\Http\Controllers\RecentlyViewedController;
use App\Modules\Engagement\Http\Controllers\SavedSearchController;
use App\Modules\Engagement\Http\Controllers\SellerController;
use App\Modules\Identity\Http\Controllers\AuthController;
use App\Modules\Identity\Http\Controllers\BusinessController;
use App\Modules\Identity\Http\Controllers\ConsentController;
use App\Modules\Identity\Http\Controllers\DeviceController;
use App\Modules\Identity\Http\Controllers\EmailVerificationController;
use App\Modules\Identity\Http\Controllers\MeController;
use App\Modules\Identity\Http\Controllers\NotificationPreferenceController;
use App\Modules\Listings\Http\Controllers\DraftController;
use App\Modules\Listings\Http\Controllers\ListingController;
use App\Modules\Listings\Http\Controllers\ListingSearchController;
use App\Modules\Listings\Http\Controllers\MediaController;
use App\Modules\Listings\Http\Controllers\MyListingsController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    // ── Catalog (عامة، بدون مصادقة) ────────────────────────────────────
    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('categories/{id}', [CategoryController::class, 'show'])->where('id', '[0-9a-hjkmnp-tv-z]{26}');
    Route::get('locations', [LocationController::class, 'index']);

    // ── Listings ───────────────────────────────────────────────────────
    Route::get('listings', [ListingSearchController::class, 'search']);
    Route::get('listings/{id}', [ListingController::class, 'show'])->where('id', '[0-9a-hjkmnp-tv-z]{26}');
    Route::get('listings/{id}/similar', [ListingController::class, 'similar'])->where('id', '[0-9a-hjkmnp-tv-z]{26}');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('listings', [ListingController::class, 'store'])->middleware('idempotency');
        Route::patch('listings/{id}', [ListingController::class, 'update'])->where('id', '[0-9a-hjkmnp-tv-z]{26}');
        Route::delete('listings/{id}', [ListingController::class, 'destroy'])->where('id', '[0-9a-hjkmnp-tv-z]{26}');

        Route::post('listings/{id}/reserve', fn (Request $r, string $id) => app(ListingController::class)->transition($r, $id, 'reserve'))->where('id', '[0-9a-hjkmnp-tv-z]{26}');
        Route::post('listings/{id}/unreserve', fn (Request $r, string $id) => app(ListingController::class)->transition($r, $id, 'unreserve'))->where('id', '[0-9a-hjkmnp-tv-z]{26}');
        Route::post('listings/{id}/sell', fn (Request $r, string $id) => app(ListingController::class)->transition($r, $id, 'sell'))->where('id', '[0-9a-hjkmnp-tv-z]{26}');
        Route::post('listings/{id}/reactivate', fn (Request $r, string $id) => app(ListingController::class)->transition($r, $id, 'reactivate'))->where('id', '[0-9a-hjkmnp-tv-z]{26}');
        Route::post('listings/{id}/extend', [ListingController::class, 'extend'])->where('id', '[0-9a-hjkmnp-tv-z]{26}');

        Route::get('me/listings', [MyListingsController::class, 'index']);
        Route::get('me/listings/stats', [MyListingsController::class, 'stats']);

        // ── Drafts ────────────────────────────────────────────────────
        Route::get('drafts', [DraftController::class, 'index']);
        Route::post('drafts', [DraftController::class, 'store']);
        Route::get('drafts/{id}', [DraftController::class, 'show'])->where('id', '[0-9a-hjkmnp-tv-z]{26}');
        Route::put('drafts/{id}', [DraftController::class, 'update'])->where('id', '[0-9a-hjkmnp-tv-z]{26}');
        Route::delete('drafts/{id}', [DraftController::class, 'destroy'])->where('id', '[0-9a-hjkmnp-tv-z]{26}');
        Route::post('drafts/{id}/publish', [DraftController::class, 'publish'])->middleware('idempotency')->where('id', '[0-9a-hjkmnp-tv-z]{26}');

        // ── Media ─────────────────────────────────────────────────────
        Route::post('media', [MediaController::class, 'store'])->middleware('idempotency');
        Route::get('media/{id}', [MediaController::class, 'show'])->where('id', '[0-9a-hjkmnp-tv-z]{26}');
        Route::delete('media/{id}', [MediaController::class, 'destroy'])->where('id', '[0-9a-hjkmnp-tv-z]{26}');

        // ── Engagement ────────────────────────────────────────────────
        Route::get('favorites', [FavoriteController::class, 'index']);
        Route::put('favorites/{listingId}', [FavoriteController::class, 'store'])->where('listingId', '[0-9a-hjkmnp-tv-z]{26}');
        Route::delete('favorites/{listingId}', [FavoriteController::class, 'destroy'])->where('listingId', '[0-9a-hjkmnp-tv-z]{26}');

        Route::get('saved-searches', [SavedSearchController::class, 'index']);
        Route::post('saved-searches', [SavedSearchController::class, 'store']);
        Route::patch('saved-searches/{id}', [SavedSearchController::class, 'update'])->where('id', '[0-9a-hjkmnp-tv-z]{26}');
        Route::delete('saved-searches/{id}', [SavedSearchController::class, 'destroy'])->where('id', '[0-9a-hjkmnp-tv-z]{26}');

        Route::get('follows', [FollowController::class, 'index']);
        Route::put('follows/{userId}', [FollowController::class, 'store'])->where('userId', '[0-9a-hjkmnp-tv-z]{26}');
        Route::delete('follows/{userId}', [FollowController::class, 'destroy'])->where('userId', '[0-9a-hjkmnp-tv-z]{26}');

        Route::get('recently-viewed', [RecentlyViewedController::class, 'index']);
        Route::delete('recently-viewed', [RecentlyViewedController::class, 'destroy']);
    });

    // ── صفحات البائعين العامة ─────────────────────────────────────────
    Route::get('users/{id}', [SellerController::class, 'show'])->where('id', '[0-9a-hjkmnp-tv-z]{26}');
    Route::get('users/{id}/listings', [SellerController::class, 'listings'])->where('id', '[0-9a-hjkmnp-tv-z]{26}');
    Route::get('users/{id}/stats', [SellerController::class, 'stats'])->where('id', '[0-9a-hjkmnp-tv-z]{26}');
});
