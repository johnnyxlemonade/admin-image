<?php

declare(strict_types=1);

namespace Lemonade\Image\Tests\Unit;

use Lemonade\Framework\Api\Endpoint\ApiAccess;
use Lemonade\Framework\Api\Endpoint\ApiEndpointRegistrar;
use Lemonade\Framework\Api\Endpoint\ApiEndpointRegistry;
use Lemonade\Framework\Routing\Router;
use Lemonade\Image\Http\Controller\ImageController;
use Lemonade\Image\Routing\ImageRouteRegistrar;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

/**
 * Verifies image delivery registration through the Framework API registry
 */
final class ImageApiEndpointProviderTest extends TestCase
{
    /**
     * Verifies public access, the image URL, and matched route parameters
     */
    public function testItRegistersPublicParameterizedImageEndpoint(): void
    {
        $endpoints = new ApiEndpointRegistry();
        (new ImageRouteRegistrar())->register($endpoints);

        $endpoint = $endpoints->findByName('api.image.show');

        self::assertNotNull($endpoint);
        self::assertSame(ApiAccess::Public, $endpoint->access());
        self::assertSame('/image/{variant}/{fileId}', $endpoint->path());
        self::assertSame(ImageController::class, $endpoint->controllerAction()->controllerClass());

        $router = new Router();
        (new ApiEndpointRegistrar($router, $endpoints))->registerRoutes('/api');
        $match = $router->match(new ServerRequest('GET', '/api/image/news-detail/42'));

        self::assertSame('api.image.show', $match->name());
        self::assertSame('/api/image/{variant}/{fileId}', $match->path());
        self::assertSame('GET', $match->method());
        self::assertSame([
            'variant' => 'news-detail',
            'fileId' => '42',
        ], $match->params());
    }
}
