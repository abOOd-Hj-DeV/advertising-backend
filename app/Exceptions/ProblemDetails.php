<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

/**
 * RFC 9457 Problem Details لكل أخطاء /api/*.
 *
 * الغلاف: {type, title, status, detail, code, errors?, instance}
 * - `code` رمز ثابت بصيغة `domain.reason` يترجمه تطبيق Flutter
 * - `title`/`detail` عربيان افتراضيًا (Accept-Language: ar)
 */
final class ProblemDetails
{
    public static function register(Exceptions $exceptions): void
    {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*')
        );

        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return self::toResponse($e, $request);
        });
    }

    public static function toResponse(Throwable $e, Request $request): JsonResponse
    {
        [$status, $code, $title, $detail, $errors] = self::describe($e);

        $problem = array_filter([
            'type' => config('app.url').'/problems/'.$code,
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
            'code' => $code,
            'errors' => $errors,
            'instance' => $request->fullUrl(),
        ], static fn ($v) => $v !== null);

        $response = new JsonResponse($problem, $status, ['Content-Type' => 'application/problem+json']);

        if ($e instanceof TooManyRequestsHttpException) {
            $retry = $e->getHeaders()['Retry-After'] ?? null;
            if ($retry !== null) {
                $response->headers->set('Retry-After', (string) $retry);
            }
        }

        return $response;
    }

    /**
     * @return array{0:int,1:string,2:string,3:string,4:?array<string,list<string>>}
     */
    private static function describe(Throwable $e): array
    {
        return match (true) {
            $e instanceof ValidationException => [
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'request.validation_failed',
                'بيانات غير صالحة',
                'راجع الحقول المحددة في errors.',
                $e->errors(),
            ],
            $e instanceof AuthenticationException => [
                Response::HTTP_UNAUTHORIZED,
                'auth.unauthenticated',
                'مطلوب تسجيل الدخول',
                'الجلسة غير موجودة أو منتهية.',
                null,
            ],
            $e instanceof AuthorizationException => [
                Response::HTTP_FORBIDDEN,
                'auth.forbidden',
                'غير مصرح',
                'لا تملك صلاحية تنفيذ هذا الإجراء.',
                null,
            ],
            $e instanceof ModelNotFoundException => [
                Response::HTTP_NOT_FOUND,
                'resource.not_found',
                'غير موجود',
                'المورد المطلوب غير موجود أو حُذف.',
                null,
            ],
            $e instanceof UniqueConstraintViolationException => [
                Response::HTTP_CONFLICT,
                'resource.conflict',
                'تعارض',
                'قيمة مكررة أو تعارض مع سجل موجود.',
                null,
            ],
            $e instanceof TooManyRequestsHttpException => [
                Response::HTTP_TOO_MANY_REQUESTS,
                'rate_limit.exceeded',
                'طلبات كثيرة',
                'أبطئ وحاول مجددًا بعد المهلة المحددة.',
                null,
            ],
            $e instanceof HttpExceptionInterface => [
                $e->getStatusCode(),
                'http.'.$e->getStatusCode(),
                Response::$statusTexts[$e->getStatusCode()] ?? 'خطأ',
                $e->getStatusCode() < 500 ? $e->getMessage() : 'خطأ داخلي في الخادم.',
                null,
            ],
            default => [
                Response::HTTP_INTERNAL_SERVER_ERROR,
                'server.error',
                'خطأ داخلي',
                config('app.debug') ? $e->getMessage() : 'حدث خطأ غير متوقع. أعد المحاولة لاحقًا.',
                null,
            ],
        };
    }
}
