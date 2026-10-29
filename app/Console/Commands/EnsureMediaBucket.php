<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Aws\S3\S3Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * إنشاء حاوية الوسائط على S3/MinIO إن لم تكن موجودة.
 * تستدعيها خدمة minio-init في docker-compose قبل بدء التطبيق.
 */
class EnsureMediaBucket extends Command
{
    protected $signature = 'media:ensure-bucket';

    protected $description = 'Create the S3 media bucket when missing';

    public function handle(): int
    {
        $bucket = (string) config('filesystems.disks.s3.bucket');
        if ($bucket === '') {
            $this->warn('No s3 bucket configured — skipping.');

            return self::SUCCESS;
        }

        $client = new S3Client([
            'version' => 'latest',
            'region' => (string) config('filesystems.disks.s3.region'),
            'endpoint' => config('filesystems.disks.s3.endpoint'),
            'use_path_style_endpoint' => (bool) config('filesystems.disks.s3.use_path_style_endpoint', true),
            'credentials' => [
                'key' => (string) config('filesystems.disks.s3.key'),
                'secret' => (string) config('filesystems.disks.s3.secret'),
            ],
        ]);

        try {
            $client->createBucket(['Bucket' => $bucket]);
            $this->info("Bucket {$bucket} created.");
        } catch (\Throwable $e) {
            // Bucket already exists or transient S3 error — fine at boot.
            Log::debug('ensure-bucket: '.$e->getMessage());
        }

        return self::SUCCESS;
    }
}
