<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Models\UserConsent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

class ConsentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $consents = $request->user()->consents()
            ->latest()
            ->get(['purpose', 'version', 'granted', 'created_at']);

        return new JsonResponse($consents->map(fn (UserConsent $c) => [
            'purpose' => $c->purpose,
            'version' => $c->version,
            'granted' => $c->granted,
            'at' => $c->created_at->toIso8601String(),
        ]));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'purpose' => ['required', Rule::in(UserConsent::PURPOSES)],
            'version' => ['required', 'string', 'max:20'],
            'granted' => ['required', 'boolean'],
        ]);

        $consent = $request->user()->consents()->create([
            ...$data,
            'ip_hash' => hash('sha256', (string) $request->ip()),
        ]);

        return new JsonResponse(['id' => $consent->id], Response::HTTP_CREATED);
    }
}
