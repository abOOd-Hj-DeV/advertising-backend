<?php

declare(strict_types=1);

namespace App\Modules\Listings\Http\Controllers;

use App\Modules\Listings\Jobs\ProcessMediaJob;
use App\Modules\Listings\Models\Media;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class MediaController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:8192'],
        ]);

        $file = $request->file('file');
        $hash = hash_file('sha256', $file->getRealPath());
        $size = $file->getSize();

        [$width, $height] = getimagesize($file->getRealPath()) ?: [null, null];
        if (! $width || ! $height) {
            throw new HttpException(422, 'ملف صورة غير صالح.');
        }
        if ($width * $height > Media::MAX_PIXELS) {
            throw new HttpException(422, 'الصورة أكبر من 25 ميجابكسل.');
        }

        $mime = $file->getMimeType();
        if (! in_array($mime, Media::ALLOWED_MIMES, true)) {
            throw new HttpException(422, 'صيغة غير مدعومة (JPEG/PNG/WebP فقط).');
        }

        $disk = $this->disk();
        $path = 'media/'.$request->user()->id.'/'.Str::ulid().'.'.$this->extension($mime);
        Storage::disk($disk)->put($path, $file->getContent());

        $media = Media::query()->create([
            'owner_id' => $request->user()->id,
            'disk' => $disk,
            'original_path' => $path,
            'mime' => $mime,
            'bytes' => $size,
            'width' => $width,
            'height' => $height,
            'sha256' => $hash,
            'status' => Media::STATUS_UPLOADED,
        ]);

        ProcessMediaJob::dispatch($media->id);

        return new JsonResponse(['data' => $this->serialize($media)], 202);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        return new JsonResponse(['data' => $this->serialize($this->findOwned($request, $id))]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $media = $this->findOwned($request, $id);

        if ($media->isAttachedToListing()) {
            throw new HttpException(409, 'الصورة مرتبطة بإعلان — أزلها منه أولًا.');
        }

        $paths = array_merge([$media->original_path], array_values($media->variants ?? []));
        foreach ($paths as $p) {
            Storage::disk($media->disk)->delete($p);
        }
        $media->delete();

        return new JsonResponse(null, 204);
    }

    private function findOwned(Request $request, string $id): Media
    {
        $media = Media::query()->where('id', $id)->where('owner_id', $request->user()->id)->first();
        if (! $media) {
            throw new ModelNotFoundException;
        }

        return $media;
    }

    private function disk(): string
    {
        return config('filesystems.default') === 's3' ? 's3' : 'public';
    }

    private function extension(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'bin',
        };
    }

    /** @return array<string, mixed> */
    private function serialize(Media $m): array
    {
        return [
            'id' => $m->id,
            'status' => $m->status,
            'rejected_reason' => $m->rejected_reason,
            'variants' => $m->variants ? [
                'thumb' => $m->urlFor('thumb'),
                'card' => $m->urlFor('card'),
                'full' => $m->urlFor('full'),
            ] : null,
            'width' => $m->width,
            'height' => $m->height,
            'bytes' => $m->bytes,
        ];
    }
}
