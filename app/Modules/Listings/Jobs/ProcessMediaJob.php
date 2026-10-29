<?php

declare(strict_types=1);

namespace App\Modules\Listings\Jobs;

use App\Modules\Listings\Models\Media;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * معالجة الوسائط: توليد variants (thumb/card/full) بـGD.
 * تحتفظ بالأصل وتولّد JPEG مضغوطًا بعرض أقصى لكل نسخة.
 */
class ProcessMediaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(private readonly string $mediaId) {}

    public function handle(): void
    {
        $media = Media::query()->find($this->mediaId);
        if (! $media || $media->status === Media::STATUS_REJECTED) {
            return;
        }

        $media->update(['status' => Media::STATUS_PROCESSING]);

        try {
            $contents = Storage::disk($media->disk)->get($media->original_path);
            $src = imagecreatefromstring($contents);
            if (! $src) {
                throw new \RuntimeException('corrupt image data');
            }

            $variants = [];
            foreach (Media::VARIANT_SIZES as $name => $maxDim) {
                [$w, $h] = [$media->width, $media->height];
                if (! $w || ! $h) {
                    continue;
                }
                $scale = min(1.0, $maxDim / max($w, $h));
                [$nw, $nh] = [max(1, (int) ($w * $scale)), max(1, (int) ($h * $scale))];

                $dst = imagecreatetruecolor($nw, $nh);
                // خلفية بيضاء لتحويل الشفافية إلى JPEG
                imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
                imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

                ob_start();
                imagejpeg($dst, null, 82);
                $encoded = ob_get_clean();
                imagedestroy($dst);

                $vPath = 'media/'.$media->owner_id.'/'.Str::ulid()."_{$name}.jpg";
                Storage::disk($media->disk)->put($vPath, $encoded);
                $variants[$name] = $vPath;
            }
            imagedestroy($src);

            $media->update([
                'status' => Media::STATUS_READY,
                'variants' => $variants,
                'rejected_reason' => null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('media processing failed', ['media' => $this->mediaId, 'error' => $e->getMessage()]);
            $media->update([
                'status' => Media::STATUS_REJECTED,
                'rejected_reason' => 'تعذّرت معالجة الصورة.',
            ]);
        }
    }
}
