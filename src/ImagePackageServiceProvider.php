<?php

declare(strict_types=1);

namespace Lemonade\Image;

use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Image\Http\Controller\ImageController;

/**
 * Registers image delivery services; consuming applications register their own variants
 */
final readonly class ImagePackageServiceProvider implements ServiceProviderInterface
{
    /**
     * Registers the shared variant registry and request-scoped image controller
     */
    public function register(ContainerBuilderInterface $container): void
    {
        $container->singleton(ImageVariantRegistry::class, ImageVariantRegistry::class);
        $container->scoped(ImageController::class, ImageController::class);
    }
}
