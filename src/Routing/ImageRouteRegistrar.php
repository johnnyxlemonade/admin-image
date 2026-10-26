<?php

declare(strict_types=1);

namespace Lemonade\Image\Routing;

use Lemonade\Framework\Api\Endpoint\ApiAccess;
use Lemonade\Framework\Api\Endpoint\ApiEndpointMetadata;
use Lemonade\Framework\Api\Endpoint\ApiEndpointProviderInterface;
use Lemonade\Framework\Api\Endpoint\ApiEndpointRegistry;
use Lemonade\Framework\Routing\ControllerAction;
use Lemonade\Image\Http\Controller\ImageController;

/**
 * Registruje verejny API endpoint pro modulove image varianty a originaly
 */
final readonly class ImageRouteRegistrar implements ApiEndpointProviderInterface
{
    /**
     * Pripojuje canonical public image route do Framework API registry
     */
    public function register(ApiEndpointRegistry $registry): void
    {
        $registry->get(
            path: '/image/{module}/{variant}/{image}',
            controllerAction: ControllerAction::for(
                controllerClass: ImageController::class,
                method: 'show',
            ),
            name: 'api.image.show',
            summary: 'Public image delivery',
            description: 'Returns a registered image variant or its canonical original.',
            access: ApiAccess::Public,
            metadata: new ApiEndpointMetadata(
                tags: ['Images'],
                responseContentType: 'application/octet-stream',
            ),
        );
    }
}
