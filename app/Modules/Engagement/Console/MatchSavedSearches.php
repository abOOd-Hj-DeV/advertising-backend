<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Console;

use App\Modules\Engagement\Models\SavedSearch;
use App\Modules\Engagement\Models\SavedSearchMatch;
use App\Modules\Identity\Models\User;
use App\Modules\Listings\Models\Listing;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MatchSavedSearches extends Command
{
    protected $signature = 'saved-searches:match';

    protected $description = 'مطابقة الإعلانات الجديدة مع الأبحاث المحفوظة وإشعار أصحابها';

    public function handle(): int
    {
        SavedSearch::query()
            ->where('alerts_enabled', true)
            ->chunkById(50, function ($searches): void {
                foreach ($searches as $search) {
                    $since = $search->last_alerted_at ?? $search->created_at;
                    $query = Listing::query()
                        ->where('status', 'active')
                        ->where('moderation_state', 'clean')
                        ->where('created_at', '>', $since)
                        ->limit(20);

                    $filters = $search->filters ?? [];
                    if (! empty($filters['category'])) {
                        $query->where('category_id', $filters['category']);
                    }
                    if (! empty($search->query)) {
                        $query->where('title', 'like', '%'.$search->query.'%');
                    }

                    $newCount = 0;
                    foreach ($query->get() as $listing) {
                        $created = SavedSearchMatch::query()->insertOrIgnore([
                            'id' => (string) Str::ulid(),
                            'saved_search_id' => $search->id,
                            'listing_id' => $listing->id,
                            'matched_at' => now(),
                            'notified_at' => now(),
                        ]);
                        $newCount += $created ? 1 : 0;
                    }

                    if ($newCount > 0) {
                        DB::table('notifications')->insert([
                            'id' => (string) Str::uuid(),
                            'type' => 'saved_search_matches',
                            'notifiable_id' => $search->user_id,
                            'notifiable_type' => User::class,
                            'data' => json_encode([
                                'saved_search_id' => $search->id,
                                'name' => $search->name,
                                'new_matches' => $newCount,
                            ]),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $search->update(['last_alerted_at' => now()]);
                    }
                }
            });

        return self::SUCCESS;
    }
}
