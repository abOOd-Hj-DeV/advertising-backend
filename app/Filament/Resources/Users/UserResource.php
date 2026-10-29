<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Modules\Identity\Models\User;
use App\Modules\Safety\Models\AuditLog;
use App\Modules\Safety\Models\ModerationAction;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'المستخدمون';

    protected static ?string $modelLabel = 'مستخدم';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('display_name')->label('الاسم')->searchable(),
                TextColumn::make('email')->label('البريد')->searchable(),
                TextColumn::make('role')->label('الدور')->badge(),
                TextColumn::make('status')->label('الحالة')->badge()
                    ->color(fn (string $s) => match ($s) {
                        'active' => 'success', 'suspended' => 'danger', default => 'gray',
                    }),
                IconColumn::make('email_verified_at')->label('موثّق')->boolean()
                    ->getStateUsing(fn (User $u) => $u->email_verified_at !== null),
                TextColumn::make('created_at')->label('الانضمام')->dateTime(),
            ])
            ->filters([
                SelectFilter::make('role')->options(['user' => 'مستخدم', 'moderator' => 'مشرف', 'admin' => 'إدارة']),
                SelectFilter::make('status')->options(['active' => 'نشط', 'suspended' => 'معلّق', 'pending_deletion' => 'طلب حذف']),
            ])
            ->recordActions([
                Action::make('suspend')->label('تعليق')->color('danger')->icon(Heroicon::OutlinedNoSymbol)
                    ->visible(fn (User $r) => $r->status === 'active' && ! in_array($r->role, ['admin', 'moderator'], true))
                    ->form([Textarea::make('reason')->label('السبب')->required()])
                    ->action(function (User $r, array $data): void {
                        $r->update(['status' => 'suspended']);
                        ModerationAction::query()->create([
                            'moderator_id' => auth()->id(), 'target_type' => 'user',
                            'target_id' => $r->id, 'action' => 'suspend_user',
                            'statement_of_reasons' => $data['reason'],
                        ]);
                        AuditLog::record(auth()->id(), 'user.suspended', 'user', $r->id);
                        Notification::make()->title('علّق المستخدم')->success()->send();
                    }),
                Action::make('restore')->label('إعادة')->color('success')->icon(Heroicon::OutlinedCheckCircle)
                    ->visible(fn (User $r) => $r->status === 'suspended')
                    ->requiresConfirmation()
                    ->action(function (User $r): void {
                        $r->update(['status' => 'active']);
                        ModerationAction::query()->create([
                            'moderator_id' => auth()->id(), 'target_type' => 'user',
                            'target_id' => $r->id, 'action' => 'restore_user',
                        ]);
                        AuditLog::record(auth()->id(), 'user.restored', 'user', $r->id);
                        Notification::make()->title('أُعيد المستخدم')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageUsers::route('/')];
    }
}
