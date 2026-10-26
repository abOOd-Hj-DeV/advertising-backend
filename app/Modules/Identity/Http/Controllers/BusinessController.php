<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Http\Resources\BusinessProfileResource;
use App\Modules\Identity\Models\BusinessProfile;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class BusinessController extends Controller
{
    public function show(Request $request): BusinessProfileResource
    {
        $profile = $this->profileFor($request);

        if (! $profile) {
            throw new ModelNotFoundException;
        }

        return new BusinessProfileResource($profile);
    }

    public function status(Request $request): JsonResponse
    {
        $profile = $this->profileFor($request);

        return new JsonResponse([
            'status' => $profile->status ?? 'none',
            'review_note' => $profile?->review_note,
        ]);
    }

    /**
     * تقديم/تحديث الملف التجاري — تعديل جوهري بعد التوثيق يعيد pending.
     * أول تقديم يحوّل الحساب إلى تجاري.
     */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'legal_name' => ['nullable', 'string', 'max:120'],
            'store_name' => ['required', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:200'],
            'governorate' => ['required', 'string', 'max:60'],
            'city' => ['required', 'string', 'max:60'],
            'public_phone' => ['required', 'string', 'max:20'],
            'public_email' => ['nullable', 'email', 'max:190'],
            'register_number' => ['nullable', 'string', 'max:40'],
            'compliance_confirmed' => ['required', 'accepted'],
        ]);

        $user = $request->user();

        $profile = DB::transaction(function () use ($user, $data): BusinessProfile {
            $profile = $user->businessProfile()->first();

            if (! $profile) {
                $profile = $user->businessProfile()->create([
                    ...$data,
                    'status' => BusinessProfile::STATUS_PENDING,
                ]);
                $user->forceFill(['account_type' => User::ACCOUNT_COMMERCIAL])->save();

                return $profile;
            }

            $materialChanged = collect(BusinessProfile::MATERIAL_FIELDS)
                ->contains(fn (string $f) => array_key_exists($f, $data) && $profile->{$f} != $data[$f]);

            $profile->fill($data);

            if ($profile->status === BusinessProfile::STATUS_VERIFIED && $materialChanged) {
                $profile->status = BusinessProfile::STATUS_PENDING;
                $profile->verified_at = null;
                $profile->verified_by = null;
                $profile->review_note = null;
            }
            if ($profile->status === BusinessProfile::STATUS_REJECTED) {
                // إعادة تقديم بعد الرفض → مراجعة جديدة
                $profile->status = BusinessProfile::STATUS_PENDING;
            }

            $profile->save();

            return $profile;
        });

        return (new BusinessProfileResource($profile))
            ->response()
            ->setStatusCode(200);
    }

    private function profileFor(Request $request): ?BusinessProfile
    {
        return BusinessProfile::query()
            ->where('user_id', $request->user()->id)
            ->first();
    }
}
