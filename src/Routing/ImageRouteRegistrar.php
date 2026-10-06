<?php

declare(strict_types=1);

namespace Lemonade\Image\Routing;

use Lemonade\Framework\Routing\ControllerAction;
use Lemonade\Framework\Routing\Router;
use Lemonade\Framework\Routing\RouteRegistrarInterface;
use Lemonade\Image\Http\Controller\ImageController;

/**
 * Registruje jedinou verejnou route pro modulove image varianty
 */
final class ImageRouteRegistrar implements RouteRegistrarInterface
{
    /**
     * Vraci stabilni identifikator package-owned image route contribution
     */
    public function id(): string
    {
        return 'image.public-api';
    }

    /**
     * Vraci neutralni poradi pro verejnou package route bez host precedence
     */
    public function priority(): int
    {
        return 0;
    }

    /**
     * Pripojuje canonical API image route do host routeru
     */
    public function registerRoutes(Router $router): void
    {
        $router->getNamed(
            name: 'api.image.show',
            path: '/api/image/{module}/{variant}/{image}',
            action: ControllerAction::for(
                controllerClass: ImageController::class,
                method: 'show',
            ),
        );
    }
}
