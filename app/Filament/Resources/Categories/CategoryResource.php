<?php

namespace App\Filament\Resources\Categories;

use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Modules\Catalog\Models\Category;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $navigationLabel = 'التصنيفات';

    protected static ?string $modelLabel = 'تصنيف';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name_ar')->label('الاسم العربي')->required()->maxLength(100),
            TextInput::make('slug')->label('الرابط')->required()->maxLength(100),
            Select::make('parent_id')->label('التصنيف الأب')
                ->options(Category::query()->whereNull('parent_id')->pluck('name_ar', 'id'))
                ->searchable(),
            TextInput::make('sort_order')->label('الترتيب')->numeric()->default(0),
            Toggle::make('is_active')->label('فعّال')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name_ar')->label('الاسم')->searchable(),
                TextColumn::make('parent.name_ar')->label('الأب')->placeholder('—'),
                TextColumn::make('slug')->label('الرابط'),
                IconColumn::make('is_active')->label('فعّال')->boolean(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCategories::route('/'),
            'create' => CreateCategory::route('/create'),
            'edit' => EditCategory::route('/{record}/edit'),
        ];
    }
}
