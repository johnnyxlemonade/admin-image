<?php

declare(strict_types=1);

namespace Lemonade\Image;

use Lemonade\Framework\Image\Value\ImageFormat;

/**
 * Nese overena metadata canonical originalu ulozeneho Framework image pipeline
 */
final readonly class ImageOriginalMetadata
{
    /**
     * Nastavuje puvodni nazev, skutecny format, velikost a rozmery ulozeneho originalu
     */
    public function __construct(
        private string $originalFilename,
        private ImageFormat $format,
        private int $size,
        private int $width,
        private int $height,
    ) {}

    /**
     * Vraci puvodni nazev souboru bez filesystemove cesty
     */
    public function originalFilename(): string
    {
        return $this->originalFilename;
    }

    /**
     * Vraci Frameworkem overeny format ulozeneho originalu
     */
    public function format(): ImageFormat
    {
        return $this->format;
    }

    /**
     * Vraci velikost ulozeneho originalu v bytech
     */
    public function size(): int
    {
        return $this->size;
    }

    /**
     * Vraci sirku ulozeneho originalu v pixelech
     */
    public function width(): int
    {
        return $this->width;
    }

    /**
     * Vraci vysku ulozeneho originalu v pixelech
     */
    public function height(): int
    {
        return $this->height;
    }
}
