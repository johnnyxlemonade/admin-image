<?php

declare(strict_types=1);

namespace Lemonade\Image;

use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Container\ContainerInterface;
use Lemonade\Framework\Core\BootableServiceProviderInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Image\Value\ImageDimensions;
use Lemonade\Framework\Image\Value\ImageFormat;
use Lemonade\Framework\Image\Value\ImageQuality;
use Lemonade\Framework\Image\Value\ImageScalePolicy;
use Lemonade\Framework\Image\Value\ImageVariantDefinition;
use Lemonade\Framework\Image\Value\ImageVariantMode;
use Lemonade\Image\Http\Controller\ImageController;

/**
 * Registruje sdílenou image infrastrukturu a základní veřejné varianty používané napříč moduly
 */
final readonly class ImagePackageServiceProvider implements ServiceProviderInterface, BootableServiceProviderInterface
{
    /**
     * Registruje registry variant a image controller
     */
    public function register(ContainerBuilderInterface $container): void
    {
        $container->singleton(ImageVariantRegistry::class, ImageVariantRegistry::class);
        $container->scoped(ImageController::class, ImageController::class);
    }

    /**
     * Registruje canonical veřejné varianty dostupné všem modulům
     *
     * Moduly mohou nad touto společnou sadou registrovat vlastní varianty
     * pro své specifické prezentační potřeby
     */
    public function boot(ContainerInterface $container): void
    {
        $variants = $container->get(ImageVariantRegistry::class);

        // Kompaktní čtvercový náhled pro tabulkové a seznamové přehledy
        $variants->register(
            variant: 'datagrid',
            definition: new ImageVariantDefinition(
                dimensions: new ImageDimensions(width: 36, height: 36),
                format: ImageFormat::Webp,
                quality: ImageQuality::fromInt(value: 82),
            ),
        );

        // Univerzální náhled pro media picker a další kompaktní prezentaci
        $variants->register(
            variant: 'thumbnail',
            definition: new ImageVariantDefinition(
                dimensions: new ImageDimensions(width: 160, height: 160),
                format: ImageFormat::Webp,
                quality: ImageQuality::fromInt(value: 82),
            ),
        );

        // Větší administrativní preview bez zvětšování menších originálů
        $variants->register(
            variant: 'preview',
            definition: new ImageVariantDefinition(
                dimensions: new ImageDimensions(width: 960, height: 540),
                format: ImageFormat::Webp,
                quality: ImageQuality::fromInt(value: 85),
                mode: ImageVariantMode::Contain,
                scalePolicy: ImageScalePolicy::DownscaleOnly,
            ),
        );
    }
}
