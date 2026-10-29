<?php

declare(strict_types=1);

namespace App\Modules\Listings\Console;

use App\Modules\Listings\Models\Listing;
use App\Modules\Listings\Models\ListingStatusEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * إعلانات تجاوزت expires_at → expired، وإشعار المالك (لاحقًا عبر Notifications).
 */
class ExpireListings extends Command
{
    protected $signature = 'listings:expire';

    protected $description = 'تحويل الإعلانات المنتهية الصلاحية إلى حالة expired';

    public function handle(): int
    {
        $count = 0;

        Listing::query()
            ->where('status', Listing::STATUS_ACTIVE)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->chunkById(200, function ($listings) use (&$count): void {
                foreach ($listings as $listing) {
                    DB::transaction(function () use ($listing): void {
                        $listing->status = Listing::STATUS_EXPIRED;
                        $listing->version++;
                        $listing->save();
                        $listing->statusEvents()->create([
                            'from_status' => Listing::STATUS_ACTIVE,
                            'to_status' => Listing::STATUS_EXPIRED,
                            'actor_id' => null,
                            'actor_type' => ListingStatusEvent::ACTOR_SYSTEM,
                            'reason' => 'انتهت صلاحية الإعلان',
                            'created_at' => now(),
                        ]);
                    });
                    $count++;
                }
            });

        $this->info("{$count} إعلانًا حُوّل إلى expired.");

        return self::SUCCESS;
    }
}
