<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Models\Category;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class CategoryController extends Controller
{
    /** شجرة التصنيفات الكاملة (آباء + أبناء، بدون خصائص) — تُكاش لدى التطبيق */
    public function index(): JsonResponse
    {
        $roots = Category::query()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->with(['children' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('sort_order')
            ->get();

        return new JsonResponse(['data' => $roots->map(fn (Category $c) => self::node($c))->all()]);
    }

    /** تصنيف واحد بأبنائه وخصائصه (خصائص الأب تُورث للأبناء في طبقة التطبيق) */
    public function show(string $id): JsonResponse
    {
        $category = Category::query()
            ->where('is_active', true)
            ->with([
                'children' => fn ($q) => $q->where('is_active', true),
                'attributes.options',
                'parent.attributes.options',
            ])
            ->find($id);

        if (! $category) {
            throw new ModelNotFoundException;
        }

        $inherited = $category->parent?->attributes ?? collect();

        return new JsonResponse(['data' => [
            ...self::node($category),
            'attributes' => $category->attributes->map(fn ($a) => self::attribute($a))->all(),
            'inherited_attributes' => $inherited->map(fn ($a) => self::attribute($a))->all(),
        ]]);
    }

    /** @return array<string, mixed> */
    private static function node(Category $c): array
    {
        return [
            'id' => $c->id,
            'slug' => $c->slug,
            'name' => $c->name_ar,
            'icon' => $c->icon,
            'allows_shipping' => $c->allows_shipping,
            'allows_wanted' => $c->allows_wanted,
            'children' => $c->relationLoaded('children')
                ? $c->children->map(fn (Category $ch) => [
                    'id' => $ch->id,
                    'slug' => $ch->slug,
                    'name' => $ch->name_ar,
                    'icon' => $ch->icon,
                    'allows_shipping' => $ch->allows_shipping,
                    'allows_wanted' => $ch->allows_wanted,
                ])->all()
                : [],
        ];
    }

    /** @return array<string, mixed> */
    private static function attribute($a): array
    {
        return [
            'key' => $a->key,
            'label' => $a->label_ar,
            'type' => $a->data_type,
            'required' => $a->is_required,
            'filterable' => $a->is_filterable,
            'options' => $a->relationLoaded('options')
                ? $a->options->map(fn ($o) => ['value' => $o->value, 'label' => $o->label_ar])->all()
                : [],
        ];
    }
}
