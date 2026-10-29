<?php

declare(strict_types=1);

namespace App\Modules\Listings\Http\Requests;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\CategoryAttribute;
use App\Modules\Catalog\Models\CategoryAttributeOption;
use App\Modules\Catalog\Models\Location;
use App\Modules\Listings\Models\Listing;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * تحقق حمولة الإعلان — يستخدمها POST /listings و /drafts/{id}/publish و PATCH.
 * يتحقق من الخصائص الديناميكية مقابل مخطط التصنيف (شامل الموروثة).
 */
final class ListingPayload
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed> الحقول الصالحة المطبعة
     *
     * @throws ValidationException
     */
    public static function validate(array $input): array
    {
        $rules = [
            'category_id' => ['required', 'string', 'size:26'],
            'offer_type' => ['required', 'in:'.Listing::OFFER.','.Listing::WANTED],
            'title' => ['required', 'string', 'min:3', 'max:'.Listing::TITLE_MAX],
            'description' => ['required', 'string', 'max:'.Listing::DESCRIPTION_MAX],
            'price_cents' => ['nullable', 'integer', 'min:0', 'max:99999999999'],
            'currency' => ['sometimes', 'in:SYP,USD'],
            'price_type' => ['required', 'in:fixed,negotiable,free'],
            'location_id' => ['required', 'string', 'size:26'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'shipping_available' => ['sometimes', 'boolean'],
            'carrier' => ['nullable', 'string', 'max:60'],
            'attributes' => ['sometimes', 'array'],
            'media_ids' => ['sometimes', 'array', 'max:'.Listing::MAX_IMAGES],
            'media_ids.*' => ['string', 'size:26'],
            'cover_media_id' => ['nullable', 'string', 'size:26'],
        ];

        $data = Validator::make($input, $rules)->validate();

        $category = Category::query()->where('is_active', true)->find($data['category_id']);
        if (! $category) {
            throw ValidationException::withMessages(['category_id' => 'تصنيف غير موجود.']);
        }

        $location = Location::query()->find($data['location_id']);
        if (! $location) {
            throw ValidationException::withMessages(['location_id' => 'موقع غير موجود.']);
        }

        if ($data['price_type'] === 'free') {
            $data['price_cents'] = null;
        } elseif (! isset($data['price_cents'])) {
            throw ValidationException::withMessages(['price_cents' => 'السعر مطلوب لهذا النوع.']);
        }

        $data['attributes'] = self::validateAttributes(
            $category,
            is_array($input['attributes'] ?? null) ? $input['attributes'] : []
        );

        // تقريب الإحداثيات ~1 كم (خصوصية)
        foreach (['lat', 'lng'] as $k) {
            if (isset($data[$k])) {
                $data[$k] = round((float) $data[$k], 2);
            }
        }

        return $data;
    }

    /**
     * @return array<string, array{attribute:CategoryAttribute,option:?CategoryAttributeOption,value:mixed}>
     */
    private static function validateAttributes(Category $category, array $given): array
    {
        $defs = CategoryAttribute::query()
            ->where('category_id', $category->id)
            ->when($category->parent_id, fn ($q) => $q->orWhere('category_id', $category->parent_id))
            ->with('options')
            ->get()
            ->keyBy('key');

        $errors = [];
        $resolved = [];

        foreach ($defs as $key => $def) {
            $present = array_key_exists($key, $given);
            if (! $present) {
                if ($def->is_required) {
                    $errors["attributes.{$key}"] = "الحقل «{$def->label_ar}» مطلوب.";
                }

                continue;
            }

            $raw = $given[$key];
            $resolved[$key] = match ($def->data_type) {
                'enum' => self::resolveEnum($def, $raw, $key, $errors),
                'int', 'decimal' => self::resolveNumber($def, $raw, $key, $errors),
                'bool' => self::resolveBool($def, $raw, $key, $errors),
                default => self::resolveText($def, $raw, $key, $errors),
            };
        }

        foreach ($given as $key => $v) {
            if (! $defs->has($key)) {
                $errors["attributes.{$key}"] = 'خاصية غير معروفة لهذا التصنيف.';
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return array_filter($resolved);
    }

    private static function resolveEnum($def, mixed $raw, string $key, array &$errors): ?array
    {
        if (! is_string($raw)) {
            $errors["attributes.{$key}"] = 'قيمة غير صالحة.';

            return null;
        }
        $option = $def->options->firstWhere('value', $raw)
            ?? $def->options->firstWhere('label_ar', $raw);
        if (! $option) {
            $errors["attributes.{$key}"] = 'خيار غير معروف.';

            return null;
        }

        return ['attribute' => $def, 'option' => $option, 'value' => null];
    }

    private static function resolveNumber($def, mixed $raw, string $key, array &$errors): ?array
    {
        if (! is_numeric($raw)) {
            $errors["attributes.{$key}"] = 'يجب أن تكون رقمًا.';

            return null;
        }

        return ['attribute' => $def, 'option' => null, 'value' => (float) $raw];
    }

    private static function resolveBool($def, mixed $raw, string $key, array &$errors): ?array
    {
        $b = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($b === null) {
            $errors["attributes.{$key}"] = 'يجب أن تكون true/false.';

            return null;
        }

        return ['attribute' => $def, 'option' => null, 'value' => $b];
    }

    private static function resolveText($def, mixed $raw, string $key, array &$errors): ?array
    {
        if (! is_string($raw) || mb_strlen($raw) > 500) {
            $errors["attributes.{$key}"] = 'نص غير صالح (≤500 حرف).';

            return null;
        }

        return ['attribute' => $def, 'option' => null, 'value' => $raw];
    }
}
