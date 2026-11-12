<?php

declare(strict_types=1);

namespace Lemonade\Image;

use Lemonade\Framework\Routing\UrlGenerator;

/**
 * Builds public image URLs from registered variants and global file IDs
 */
final readonly class ImageUrlGenerator
{
    public const ORIGINAL_ROUTE_VARIANT = 'original';

    /**
     * Connects URL generation to the registered image variants
     */
    public function __construct(
        private UrlGenerator $urls,
        private ImageVariantRegistry $variants,
    ) {}

    /**
     * Returns an extensionless URL; the registered variant determines the output format
     */
    public function url(string $variant, int $fileId): string
    {
        $this->variants->require($variant);
        if ($fileId < 1) {
            throw new \InvalidArgumentException('Image file ID must be positive.');
        }

        return $this->urls->route('api.image.show', [
            'variant' => $variant,
            'fileId' => $fileId,
        ]);
    }

    /**
     * Returns the stored original URL without requiring a variant definition
     */
    public function originalUrl(int $fileId): string
    {
        if ($fileId < 1) {
            throw new \InvalidArgumentException('Image file ID must be positive.');
        }

        return $this->urls->route('api.image.show', [
            'variant' => self::ORIGINAL_ROUTE_VARIANT,
            'fileId' => $fileId,
        ]);
    }
}
