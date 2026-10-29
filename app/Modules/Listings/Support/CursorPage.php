<?php

declare(strict_types=1);

namespace App\Modules\Listings\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * ترقيم بمؤشر معتم: base64(json [last_sort_value, last_id]).
 * يدعم ترتيب تصاعدي وتنازلي على عمود + id ككاسر تعادل.
 */
final class CursorPage
{
    public const DEFAULT_LIMIT = 20;

    public const MAX_LIMIT = 50;

    /**
     * @return array{data:Collection, next_cursor:?string}
     */
    public static function paginate(Builder $query, string $column, string $direction, ?string $cursor, int $limit = self::DEFAULT_LIMIT): array
    {
        $limit = max(1, min($limit, self::MAX_LIMIT));
        $dir = $direction === 'asc' ? 'asc' : 'desc';
        $cmp = $dir === 'asc' ? '>' : '<';

        if ($cursor) {
            $decoded = json_decode(base64_decode($cursor, true) ?: '', true);
            if (is_array($decoded) && isset($decoded[0], $decoded[1])) {
                [$value, $id] = $decoded;
                $query->where(function ($q) use ($column, $cmp, $value, $id): void {
                    $q->where($column, $cmp, $value)
                        ->orWhere(function ($q2) use ($column, $value, $cmp, $id): void {
                            $q2->where($column, '=', $value)->where('id', $cmp, $id);
                        });
                });
            }
        }

        $query->orderBy($column, $dir)->orderBy('id', $dir);
        $items = $query->limit($limit + 1)->get();

        $next = null;
        if ($items->count() > $limit) {
            $items = $items->take($limit);
            $last = $items->last();
            $next = base64_encode(json_encode([$last->getAttribute($column), $last->getKey()]));
        }

        return ['data' => $items, 'next_cursor' => $next];
    }
}
