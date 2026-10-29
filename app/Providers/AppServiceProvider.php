<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        // نقاط الكتابة الحساسة — إعلانات ورسائل وبلاغات.
        RateLimiter::for('api-writes', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        // التطبيق يقرأ التوكن من الرابط ويرسله إلى POST /auth/password/reset.
        ResetPassword::createUrlUsing(
            fn (object $user, string $token) => rtrim((string) config('app.url'), '/')
                .'/reset-password?token='.$token.'&email='.urlencode($user->getEmailForPasswordReset())
        );
    }
}
