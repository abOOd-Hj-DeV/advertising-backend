<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class LocationController extends Controller
{
    /** بحث المواقع: ?q= يطابق المدينة أو المحافظة، بدونه قائمة المحافظات بمدنها */
    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        $query = Location::query()->select(['id', 'governorate', 'city', 'district']);

        if ($q !== '') {
            $query->where(function ($w) use ($q): void {
                $w->where('city', 'like', "%{$q}%")
                    ->orWhere('governorate', 'like', "%{$q}%")
                    ->orWhere('district', 'like', "%{$q}%");
            })->orderBy('governorate')->orderBy('city');

            return new JsonResponse(['data' => $query->limit(50)->get()]);
        }

        $all = $query->orderBy('governorate')->orderBy('city')->get();
        $grouped = $all->groupBy('governorate')->map(fn ($cities, $gov) => [
            'governorate' => $gov,
            'cities' => $cities->map(fn ($l) => [
                'id' => $l->id,
                'city' => $l->city,
                'district' => $l->district,
            ])->values(),
        ])->values();

        return new JsonResponse(['data' => $grouped]);
    }
}
