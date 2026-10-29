<?php

declare(strict_types=1);

namespace App\Modules\Safety\Http\Controllers;

use App\Modules\Listings\Models\Listing;
use App\Modules\Safety\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;

class ReportController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'target_type' => ['required', 'string', 'in:'.implode(',', Report::TARGETS)],
            'target_id' => ['required', 'string', 'size:26'],
            'reason' => ['required', 'string', 'in:'.implode(',', Report::REASONS)],
            'details' => ['nullable', 'string', 'max:2000'],
            'reporter_contact' => ['nullable', 'string', 'max:255'],
            'captcha_token' => ['nullable', 'string'],
        ]);

        $user = $request->user('sanctum');
        if (! $user && empty($data['reporter_contact'])) {
            throw ValidationException::withMessages([
                'reporter_contact' => ['مطلوب وسيلة تواصل للبلاغات من غير الحسابات.'],
            ]);
        }

        // لقطة أدلة مبسّطة للهدف
        $snapshot = null;
        if ($data['target_type'] === 'listing') {
            $listing = Listing::query()->withTrashed()->find($data['target_id']);
            $snapshot = $listing ? ['title' => $listing->title, 'user_id' => $listing->user_id] : null;
        }

        Report::query()->create([
            'reporter_id' => $user?->id,
            'reporter_contact' => $user ? null : $data['reporter_contact'],
            'target_type' => $data['target_type'],
            'target_id' => $data['target_id'],
            'reason' => $data['reason'],
            'details' => $data['details'] ?? null,
            'evidence_snapshot' => $snapshot,
            'status' => 'open',
        ]);

        return new JsonResponse(null, 202);
    }
}
