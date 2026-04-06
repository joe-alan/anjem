<?php

namespace App\Services;

use Google\Cloud\Core\Exception\NotFoundException;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Storage;

class FirebaseStorageService
{
    private string $bucketName;

    public function __construct(private Storage $storage)
    {
        $this->bucketName = config('services.firebase.storage_bucket') ?? '';
    }

    public function upload(string $objectPath, string $fileContents, string $contentType): string
    {
        $bucket = $this->storage->getBucket();

        $bucket->upload($fileContents, [
            'name' => $objectPath,
            'predefinedAcl' => 'publicRead',
            'metadata' => ['contentType' => $contentType],
        ]);

        return $this->getPublicUrl($objectPath);
    }

    public function delete(string $objectPath): void
    {
        try {
            $this->storage->getBucket()->object($objectPath)->delete();
        } catch (NotFoundException) {
            // Already deleted — ignore
        } catch (\Exception $e) {
            Log::warning('Failed to delete Firebase Storage object', [
                'path' => $objectPath,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function getPublicUrl(string $objectPath): string
    {
        return 'https://storage.googleapis.com/' . $this->bucketName . '/' . $objectPath;
    }

    public function extractObjectPath(string $url): ?string
    {
        $prefix = 'https://storage.googleapis.com/' . $this->bucketName . '/';

        if (str_starts_with($url, $prefix)) {
            return substr($url, strlen($prefix));
        }

        return null;
    }

    public function isFirebaseUrl(string $url): bool
    {
        return $this->extractObjectPath($url) !== null;
    }
}
