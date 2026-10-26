<?php

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
});
