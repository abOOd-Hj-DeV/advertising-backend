<?php

namespace App\Filament\Resources\BusinessProfiles;

use App\Filament\Resources\BusinessProfiles\Pages\ManageBusinessProfiles;
use App\Modules\Identity\Models\BusinessProfile;
use App\Modules\Safety\Models\AuditLog;
use App\Modules\Safety\Models\ModerationAction;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class BusinessProfileResource extends Resource
{
    protected static ?string $model = BusinessProfile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static ?string $navigationLabel = 'تحقق التجار';

    protected static ?string $modelLabel = 'ملف تجاري';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('store_name')->label('المتجر')->searchable(),
                TextColumn::make('user.display_name')->label('المالك')->searchable(),
                TextColumn::make('user.email')->label('البريد'),
                TextColumn::make('governorate')->label('المحافظة'),
                TextColumn::make('status')->label('الحالة')->badge()
                    ->color(fn (string $state) => match ($state) {
                        'verified' => 'success', 'pending' => 'warning',
                        'rejected' => 'danger', default => 'gray',
                    }),
                TextColumn::make('created_at')->label('أُرسل')->dateTime(),
            ])
            ->filters([
                SelectFilter::make('status')->label('الحالة')->options([
                    'pending' => 'قيد المراجعة',
                    'verified' => 'موثّق',
                    'rejected' => 'مرفوض',
                    'suspended' => 'معلّق',
                ]),
            ])
            ->recordActions([
                Action::make('verify')->label('توثيق')->color('success')->icon(Heroicon::OutlinedCheckCircle)
                    ->visible(fn (BusinessProfile $r) => $r->status === 'pending')
                    ->requiresConfirmation()
                    ->action(fn (BusinessProfile $r) => self::decide($r, 'verified')),
                Action::make('reject')->label('رفض')->color('danger')->icon(Heroicon::OutlinedXCircle)
                    ->visible(fn (BusinessProfile $r) => $r->status === 'pending')
                    ->form([Textarea::make('note')->label('سبب الرفض')->required()])
                    ->action(fn (BusinessProfile $r, array $data) => self::decide($r, 'rejected', $data['note'] ?? '')),
                Action::make('suspend')->label('تعليق')->color('gray')->icon(Heroicon::OutlinedPauseCircle)
                    ->visible(fn (BusinessProfile $r) => $r->status === 'verified')
                    ->requiresConfirmation()
                    ->action(fn (BusinessProfile $r) => self::decide($r, 'suspended')),
            ]);
    }

    private static function decide(BusinessProfile $profile, string $status, string $note = ''): void
    {
        $moderator = auth()->user();
        DB::transaction(function () use ($profile, $status, $note, $moderator): void {
            $profile->update(['status' => $status, 'review_note' => $note ?: null, 'verified_at' => $status === 'verified' ? now() : null, 'verified_by' => $status === 'verified' ? $moderator->id : null]);
            ModerationAction::query()->create([
                'moderator_id' => $moderator->id,
                'target_type' => 'business_profile',
                'target_id' => $profile->id,
                'action' => $status === 'verified' ? 'verify_business' : ($status === 'rejected' ? 'reject_business' : 'warn'),
                'statement_of_reasons' => $note ?: null,
            ]);
            AuditLog::record($moderator->id, 'business.'.$status, 'business_profile', $profile->id, ['status' => $status]);
        });
        Notification::make()->title('تم تحديث حالة الملف التجاري')->success()->send();
    }

    public static function getPages(): array
    {
        return ['index' => ManageBusinessProfiles::route('/')];
    }
}
