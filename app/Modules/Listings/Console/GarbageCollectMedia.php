<?php

declare(strict_types=1);

namespace App\Modules\Listings\Console;

use App\Modules\Listings\Models\Media;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * media:gc — حذف الوسائط اليتيمة: مرفوعة ولم تُربط بأي إعلان خلال المهلة،
 * أو مرفوضة. يحذف الملفات من التخزين ثم السجل.
 */
class GarbageCollectMedia extends Command
{
    protected $signature = 'media:gc {--hours=24 : عمر الوسائط اليتيمة بالساعات قبل الحذف} {--dry-run : عرض فقط دون حذف}';

    protected $description = 'حذف الوسائط غير المرتبطة بإعلان بعد انقضاء المهلة';

    public function handle(): int
    {
        $cutoff = now()->subHours((int) $this->option('hours'));
        $dry = (bool) $this->option('dry-run');
        $count = 0;

        Media::query()
            ->where('created_at', '<', $cutoff)
            ->whereDoesntHave('listings')
            ->chunkById(200, function ($batch) use (&$count, $dry): void {
                foreach ($batch as $media) {
                    $count++;
                    if ($dry) {
                        continue;
                    }
                    $paths = array_merge([$media->original_path], array_values($media->variants ?? []));
                    foreach ($paths as $path) {
                        try {
                            Storage::disk($media->disk)->delete($path);
                        } catch (\Throwable) {
                            // الملف قد يكون محذوفًا مسبقًا؛ نتابع لحذف السجل.
                        }
                    }
                    $media->delete();
                }
            });

        $this->info(($dry ? '[dry-run] ' : '')."media:gc — {$count} وسائط يتيمة".($dry ? '' : ' حُذفت'));

        return self::SUCCESS;
    }
}
