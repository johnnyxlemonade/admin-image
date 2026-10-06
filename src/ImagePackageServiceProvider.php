<?php

declare(strict_types=1);

namespace Lemonade\Image;

use Lemonade\Framework\Container\ContainerBuilderInterface;
use Lemonade\Framework\Core\ServiceProviderInterface;
use Lemonade\Framework\Routing\RouteRegistrarInterface;
use Lemonade\Image\Http\Controller\ImageController;
use Lemonade\Image\Routing\ImageRouteRegistrar;

/**
 * Registruje shared modulovou image URL capability pred prispevky jednotlivych modulu
 */
final class ImagePackageServiceProvider implements ServiceProviderInterface
{
    /**
     * Zapisuje registry, storage resolution, URL generator a requestovy image controller
     */
    public function register(ContainerBuilderInterface $container): void
    {
        $container->singleton(ImageVariantRegistry::class, ImageVariantRegistry::class);
        $container->singleton(ImageUrlGenerator::class, ImageUrlGenerator::class);
        $container->scoped(ImageController::class, ImageController::class);
        $container->singleton(ImageRouteRegistrar::class, ImageRouteRegistrar::class);
        $container->tag(ImageRouteRegistrar::class, RouteRegistrarInterface::class);
    }
}
