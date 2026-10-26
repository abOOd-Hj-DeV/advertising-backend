<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Models\NotificationPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

class NotificationPreferenceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $prefs = $request->user()->notificationPreferences()
            ->get(['type', 'channel', 'enabled']);

        return new JsonResponse($prefs);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            '*.type' => ['required', Rule::in(NotificationPreference::TYPES)],
            '*.channel' => ['required', Rule::in(NotificationPreference::CHANNELS)],
            '*.enabled' => ['required', 'boolean'],
        ]);

        foreach ($data as $pref) {
            $request->user()->notificationPreferences()->updateOrCreate(
                ['type' => $pref['type'], 'channel' => $pref['channel']],
                ['enabled' => $pref['enabled']],
            );
        }

        return $this->index($request);
    }
}
