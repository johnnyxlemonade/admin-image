<?php

declare(strict_types=1);

namespace Lemonade\Image;

use Lemonade\Framework\Image\Value\ImageFormat;

/**
 * Exposes metadata of an original stored by the Framework image pipeline
 */
final readonly class ImageOriginalMetadata
{
    /**
     * Initializes the stored original filename, detected format, size, and dimensions
     */
    public function __construct(
        private string $originalFilename,
        private ImageFormat $format,
        private int $size,
        private int $width,
        private int $height,
    ) {}

    /**
     * Returns the original filename without a filesystem path
     */
    public function originalFilename(): string
    {
        return $this->originalFilename;
    }

    /**
     * Returns the format detected by the Framework image pipeline
     */
    public function format(): ImageFormat
    {
        return $this->format;
    }

    /**
     * Returns the stored original size in bytes
     */
    public function size(): int
    {
        return $this->size;
    }

    /**
     * Returns the stored original width in pixels
     */
    public function width(): int
    {
        return $this->width;
    }

    /**
     * Returns the stored original height in pixels
     */
    public function height(): int
    {
        return $this->height;
    }
}
