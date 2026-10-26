<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

class DeviceController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'platform' => ['required', Rule::in(['android', 'web', 'ios'])],
            'fcm_token' => ['required', 'string', 'max:1000'],
            'app_version' => ['nullable', 'string', 'max:20'],
        ]);

        $device = $request->user()->devices()->updateOrCreate(
            ['fcm_token' => $data['fcm_token']],
            [
                'platform' => $data['platform'],
                'app_version' => $data['app_version'] ?? null,
                'last_seen_at' => now(),
            ],
        );

        return new JsonResponse(['id' => $device->id], Response::HTTP_CREATED);
    }

    public function destroy(Request $request): Response
    {
        $data = $request->validate(['fcm_token' => ['required', 'string', 'max:1000']]);

        $request->user()->devices()->where('fcm_token', $data['fcm_token'])->delete();

        return response()->noContent();
    }
}
