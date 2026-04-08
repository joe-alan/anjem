<?php

namespace App\Services;

use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class ImageCompressionService
{
    private ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver());
    }

    public function compress(string $filePath, int $maxWidth, int $maxHeight, int $quality = 80): string
    {
        $image = $this->manager->read($filePath);
        $image->scaleDown(width: $maxWidth, height: $maxHeight);

        return (string) $image->toJpeg($quality);
    }

    public function compressFromString(string $imageData, int $maxWidth, int $maxHeight, int $quality = 80): string
    {
        $image = $this->manager->read($imageData);
        $image->scaleDown(width: $maxWidth, height: $maxHeight);

        return (string) $image->toJpeg($quality);
    }
}
