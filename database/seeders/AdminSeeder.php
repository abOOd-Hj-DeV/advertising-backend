<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Seeder;

/**
 * أول مشرف للوحة Filament. يقرأ ADMIN_EMAIL / ADMIN_PASSWORD / ADMIN_NAME من البيئة
 * ولا يفعل شيئًا إن لم تُضبط — لا كلمات مرور افتراضية في المستودع.
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) env('ADMIN_EMAIL', '');
        $password = (string) env('ADMIN_PASSWORD', '');

        if ($email === '' || $password === '') {
            $this->command?->warn('AdminSeeder: ADMIN_EMAIL/ADMIN_PASSWORD غير مضبوطين — تم التخطي.');

            return;
        }

        if (strlen($password) < 12) {
            throw new \RuntimeException('ADMIN_PASSWORD يجب أن تكون 12 حرفًا على الأقل.');
        }

        $user = User::query()->withTrashed()->firstOrNew(['email' => $email]);
        $user->fill([
            'display_name' => (string) env('ADMIN_NAME', 'الإدارة'),
            'account_type' => User::ACCOUNT_PRIVATE,
            'locale' => 'ar',
        ]);
        $user->password = $password;
        $user->forceFill([
            'role' => 'admin',
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => $user->email_verified_at ?? now(),
            'deleted_at' => null,
        ])->save();

        $this->command?->info("AdminSeeder: المشرف {$email} جاهز.");
    }
}
