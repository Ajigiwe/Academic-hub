<?php

namespace App\Services\Documents;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Private document store — nothing here is ever public (spec §32).
 *
 * Slice 3 keeps bytes on the local disk under storage/app/private via
 * Laravel's filesystem; the sharded content-hash keys are identical to
 * the S3 layout, so swapping in flysystem-aws-s3-v3 later is a one-line
 * disk change (same interface, same keys, same semantics).
 *
 * Keys: private/resources/8f/4c/<32-hex>.bin — original filenames never
 * appear in storage paths.
 */
class DocumentStore
{
    public function buildStorageKey(): string
    {
        $hex = hash('sha256', Str::random(16));
        return sprintf('private/resources/%s/%s/%s.bin', substr($hex, 0, 2), substr($hex, 2, 2), substr($hex, 4));
    }

    /**
     * Logical keys are S3-shaped ("private/resources/…"). Laravel's
     * "local" disk root is ALREADY storage/app/private, so the leading
     * "private/" segment is stripped for local reads/writes.
     */
    private function localPath(string $key): string
    {
        return preg_replace('/^private\//', '', $key);
    }

    public function put(string $key, string $bytes): void
    {
        Storage::disk('local')->put($this->localPath($key), $bytes);
    }

    public function get(string $key): string
    {
        $bytes = Storage::disk('local')->get($this->localPath($key));

        if ($bytes === null) {
            throw new \RuntimeException("Document not found: {$key}");
        }

        return $bytes;
    }

    public function exists(string $key): bool
    {
        return Storage::disk('local')->exists($this->localPath($key));
    }

    /** Warm cache path for a rendered document (per identity+doc hash). */
    public function cachePath(string $cacheKey): string
    {
        return 'private/cache/'.str_replace(':', '_', $cacheKey).'.pdf';
    }

    public function getCached(string $cacheKey): ?string
    {
        return Storage::disk('local')->exists($this->cachePath($cacheKey))
            ? $this->get($this->cachePath($cacheKey))
            : null;
    }

    public function putCached(string $cacheKey, string $bytes): void
    {
        $this->put($this->cachePath($cacheKey), $bytes);
    }
}
