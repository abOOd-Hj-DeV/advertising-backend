<?php

namespace App\Filament\Resources\Listings;

use App\Filament\Resources\Listings\Pages\ManageListings;
use App\Modules\Listings\Models\Listing;
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

class ListingResource extends Resource
{
    protected static ?string $model = Listing::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $navigationLabel = 'الإعلانات';

    protected static ?string $modelLabel = 'إعلان';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')->label('العنوان')->searchable()->limit(35),
                TextColumn::make('user.display_name')->label('البائع')->searchable(),
                TextColumn::make('status')->label('الحالة')->badge()
                    ->color(fn (string $s) => match ($s) {
                        'active' => 'success', 'reserved' => 'warning', 'blocked' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('moderation_state')->label('الإشراف')->badge(),
                TextColumn::make('price_cents')->label('السعر')->money(fn (Listing $r) => $r->currency ?? 'SYP', divideBy: 100),
                TextColumn::make('created_at')->label('النشر')->dateTime(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'active' => 'نشط', 'reserved' => 'محجوز', 'sold' => 'مُباع',
                    'expired' => 'منتهي', 'blocked' => 'محظور', 'deleted' => 'محذوف',
                ]),
                SelectFilter::make('moderation_state')->options([
                    'clean' => 'نظيف', 'flagged' => 'مُعلَّم', 'under_review' => 'قيد المراجعة',
                ]),
            ])
            ->recordActions([
                Action::make('block')->label('حظر الإعلان')->color('danger')->icon(Heroicon::OutlinedNoSymbol)
                    ->visible(fn (Listing $r) => $r->status !== 'blocked' && ! $r->trashed())
                    ->form([Textarea::make('reason')->label('سبب الحظر')->required()])
                    ->action(function (Listing $r, array $data): void {
                        $r->update(['status' => 'blocked', 'moderation_state' => 'under_review']);
                        ModerationAction::query()->create([
                            'moderator_id' => auth()->id(), 'target_type' => 'listing',
                            'target_id' => $r->id, 'action' => 'block_listing',
                            'statement_of_reasons' => $data['reason'],
                        ]);
                        AuditLog::record(auth()->id(), 'listing.blocked', 'listing', $r->id);
                        Notification::make()->title('حُظر الإعلان')->success()->send();
                    }),
                Action::make('restore')->label('استعادة')->color('success')->icon(Heroicon::OutlinedCheckCircle)
                    ->visible(fn (Listing $r) => $r->status === 'blocked')
                    ->requiresConfirmation()
                    ->action(function (Listing $r): void {
                        $r->update(['status' => 'active', 'moderation_state' => 'clean']);
                        ModerationAction::query()->create([
                            'moderator_id' => auth()->id(), 'target_type' => 'listing',
                            'target_id' => $r->id, 'action' => 'restore_listing',
                        ]);
                        AuditLog::record(auth()->id(), 'listing.restored', 'listing', $r->id);
                        Notification::make()->title('استُعيد الإعلان')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageListings::route('/')];
    }
}
