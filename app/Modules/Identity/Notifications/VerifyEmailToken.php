<?php

declare(strict_types=1);

namespace App\Modules\Identity\Notifications;

use App\Modules\Identity\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * تأكيد البريد برمز يُرسل ضمن رابط — التحقق عبر POST /api/v1/auth/email/verify {token}.
 * الرمز صيغته `<user_ulid>.<random>` ويُخزَّن مجزّأً في الكاش 60 دقيقة.
 */
class VerifyEmailToken extends Notification implements ShouldQueue
{
    use Queueable;

    public const TTL_SECONDS = 3600;

    public function __construct(private readonly string $token) {}

    public static function issue(User $user): void
    {
        $token = $user->id.'.'.Str::random(48);
        Cache::put(self::cacheKey($user->id), hash('sha256', $token), self::TTL_SECONDS);

        $user->notify(new self($token));
    }

    public static function cacheKey(string $userId): string
    {
        return 'email_verify:'.$userId;
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim((string) config('app.url'), '/').'/verify-email?token='.urlencode($this->token);

        return (new MailMessage)
            ->subject('تأكيد بريدك الإلكتروني — سوقنا')
            ->greeting('أهلًا '.$notifiable->display_name)
            ->line('اضغط الزر لتأكيد بريدك وتفعيل كامل حسابك. الرابط صالح 60 دقيقة.')
            ->action('تأكيد البريد', $url)
            ->line('إذا لم تسجل في سوقنا، تجاهل هذه الرسالة.');
    }
}
