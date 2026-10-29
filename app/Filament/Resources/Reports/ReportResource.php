<?php

namespace App\Filament\Resources\Reports;

use App\Filament\Resources\Reports\Pages\ManageReports;
use App\Modules\Safety\Models\AuditLog;
use App\Modules\Safety\Models\Report;
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

class ReportResource extends Resource
{
    protected static ?string $model = Report::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static ?string $navigationLabel = 'البلاغات';

    protected static ?string $modelLabel = 'بلاغ';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('target_type')->label('الهدف')->badge(),
                TextColumn::make('reason')->label('السبب')->badge(),
                TextColumn::make('reporter.email')->label('المبلّغ')->placeholder('ضيف'),
                TextColumn::make('status')->label('الحالة')->badge()
                    ->color(fn (string $s) => match ($s) {
                        'open' => 'danger', 'in_review' => 'warning',
                        'actioned' => 'success', default => 'gray',
                    }),
                TextColumn::make('details')->label('التفاصيل')->limit(40),
                TextColumn::make('created_at')->label('التاريخ')->dateTime(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'open' => 'مفتوح', 'in_review' => 'قيد المراجعة',
                    'actioned' => 'مُعالَج', 'dismissed' => 'مرفوض',
                ]),
                SelectFilter::make('target_type')->options([
                    'listing' => 'إعلان', 'user' => 'مستخدم', 'message' => 'رسالة',
                ]),
            ])
            ->recordActions([
                Action::make('review')->label('مراجعة')->color('warning')->icon(Heroicon::OutlinedEye)
                    ->visible(fn (Report $r) => $r->status === 'open')
                    ->action(fn (Report $r) => self::setStatus($r, 'in_review')),
                Action::make('actioned')->label('مُعالَج')->color('success')->icon(Heroicon::OutlinedCheck)
                    ->visible(fn (Report $r) => in_array($r->status, ['open', 'in_review'], true))
                    ->form([Textarea::make('note')->label('ملاحظة القرار')->required()])
                    ->action(fn (Report $r, array $data) => self::setStatus($r, 'actioned', $data['note'] ?? '')),
                Action::make('dismiss')->label('رفض البلاغ')->color('gray')->icon(Heroicon::OutlinedXMark)
                    ->visible(fn (Report $r) => in_array($r->status, ['open', 'in_review'], true))
                    ->form([Textarea::make('note')->label('سبب الرفض')->required()])
                    ->action(fn (Report $r, array $data) => self::setStatus($r, 'dismissed', $data['note'] ?? '')),
            ]);
    }

    private static function setStatus(Report $report, string $status, string $note = ''): void
    {
        $report->update([
            'status' => $status,
            'assigned_to' => auth()->id(),
            'resolved_at' => in_array($status, ['actioned', 'dismissed'], true) ? now() : null,
            'resolution_note' => $note ?: null,
        ]);
        AuditLog::record(auth()->id(), 'report.'.$status, 'report', $report->id, ['status' => $status]);
        Notification::make()->title('تم تحديث البلاغ')->success()->send();
    }

    public static function getPages(): array
    {
        return ['index' => ManageReports::route('/')];
    }
}
