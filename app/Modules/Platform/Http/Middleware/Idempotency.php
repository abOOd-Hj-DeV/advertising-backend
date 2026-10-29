<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Middleware;

use App\Modules\Platform\Models\IdempotencyKey;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Idempotency-Key على عمليات الكتابة: إعادة الطلب بنفس المفتاح
 * تُعيد الرد المخزّن بدل تنفيذ العملية مرتين (ازدواجية إعلانات/مدفوعات).
 * تضارب المفتاح مع حمولة مختلفة → 422.
 */
class Idempotency
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');

        if (! $key || ! $request->user()) {
            return $next($request);
        }

        $endpoint = $request->method().' '.$request->path();
        $hash = hash('sha256', $request->getContent());

        $existing = IdempotencyKey::query()
            ->where('key', $key)
            ->where('user_id', $request->user()->id)
            ->where('expires_at', '>', now())
            ->first();

        if ($existing) {
            if ($existing->endpoint !== $endpoint || $existing->request_hash !== $hash) {
                return new Response(
                    json_encode([
                        'type' => config('app.url').'/problems/idempotency.key_conflict',
                        'title' => 'تعارض مفتاح التكرار',
                        'status' => 422,
                        'detail' => 'نفس المفتاح استُخدم مع طلب مختلف.',
                        'code' => 'idempotency.key_conflict',
                        'instance' => $request->fullUrl(),
                    ], JSON_UNESCAPED_UNICODE),
                    422,
                    ['Content-Type' => 'application/problem+json']
                );
            }

            if ($existing->response_status === null) {
                // طلب متزامن سابق ما زال قيد التنفيذ
                return new Response(
                    json_encode([
                        'type' => config('app.url').'/problems/idempotency.in_progress',
                        'title' => 'الطلب قيد المعالجة',
                        'status' => 409,
                        'detail' => 'طلب مطابق ما زال ينفّذ؛ أعد المحاولة بعد لحظات.',
                        'code' => 'idempotency.in_progress',
                        'instance' => $request->fullUrl(),
                    ], JSON_UNESCAPED_UNICODE),
                    409,
                    ['Content-Type' => 'application/problem+json']
                );
            }

            $response = new Response(
                json_encode($existing->response_body, JSON_UNESCAPED_UNICODE),
                $existing->response_status,
                ['Content-Type' => 'application/json']
            );
            $response->headers->set('Idempotency-Replayed', 'true');

            return $response;
        }

        // حجز المفتاح قبل التنفيذ لمنع السباق
        try {
            DB::table('idempotency_keys')->insert([
                'key' => $key,
                'user_id' => $request->user()->id,
                'endpoint' => $endpoint,
                'request_hash' => $hash,
                'expires_at' => now()->addHours(IdempotencyKey::TTL_HOURS),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // سباق على نفس المفتاح — أعد التحقق
            return $this->handle($request, $next);
        }

        $response = $next($request);

        try {
            if ($response->getStatusCode() < 500) {
                IdempotencyKey::query()
                    ->where('key', $key)
                    ->where('user_id', $request->user()->id)
                    ->update([
                        'response_status' => $response->getStatusCode(),
                        'response_body' => json_decode($response->getContent(), true),
                        'updated_at' => now(),
                    ]);
            }
        } catch (\Throwable $e) {
            Log::warning('idempotency store failed', ['error' => $e->getMessage()]);
        }

        return $response;
    }
}
