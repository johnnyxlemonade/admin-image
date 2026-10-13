<?php

declare(strict_types=1);

namespace Lemonade\Image;

use Lemonade\Framework\Routing\UrlGenerator;

/**
 * Vytvari canonical verejne URL registrovanych modulovych image variant
 */
final readonly class ImageUrlGenerator
{
    public const ORIGINAL_ROUTE_VARIANT = 'original';

    /**
     * Nastavuje router a registry povolenych modulovych variant
     */
    public function __construct(
        private UrlGenerator $urls,
        private ImageVariantRegistry $variants,
    ) {}

    /**
     * Vraci canonical URL bez pripony; vystupni format urcuje registrovana varianta
     */
    public function url(string $module, string $variant, string $imageId): string
    {
        $this->variants->require($variant);
        $identifier = ImageIdentifier::fromString($imageId);

        return $this->urls->route('api.image.show', [
            'module' => $module,
            'variant' => $variant,
            'image' => $identifier->value(),
        ]);
    }

    /**
     * Vraci canonical verejnou URL ulozeneho originalu bez vytvareni image varianty
     */
    public function originalUrl(string $module, string $imageId): string
    {
        $identifier = ImageIdentifier::fromString($imageId);

        return $this->urls->route('api.image.show', [
            'module' => $module,
            'variant' => self::ORIGINAL_ROUTE_VARIANT,
            'image' => $identifier->value(),
        ]);
    }
}
