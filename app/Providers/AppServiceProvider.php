<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\Model;
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

        // التطبيق يقرأ التوكن من الرابط ويرسله إلى POST /auth/password/reset.
        ResetPassword::createUrlUsing(
            fn (object $user, string $token) => rtrim((string) config('app.url'), '/')
                .'/reset-password?token='.$token.'&email='.urlencode($user->getEmailForPasswordReset())
        );
    }
}
