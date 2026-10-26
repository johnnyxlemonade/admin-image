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
 * Overuje image route zaregistrovanou pres Framework API registry
 */
final class ImageApiEndpointProviderTest extends TestCase
{
    /**
     * Overuje public policy, puvodni URL a parametry canonical RouteMatch
     */
    public function testItRegistersPublicParameterizedImageEndpoint(): void
    {
        $endpoints = new ApiEndpointRegistry();
        (new ImageRouteRegistrar())->register($endpoints);

        $endpoint = $endpoints->findByName('api.image.show');

        self::assertNotNull($endpoint);
        self::assertSame(ApiAccess::Public, $endpoint->access());
        self::assertSame('/image/{module}/{variant}/{image}', $endpoint->path());
        self::assertSame(ImageController::class, $endpoint->controllerAction()->controllerClass());

        $router = new Router();
        (new ApiEndpointRegistrar($router, $endpoints))->registerRoutes('/api');
        $match = $router->match(new ServerRequest('GET', '/api/image/cms.news/news-detail/42'));

        self::assertSame('api.image.show', $match->name());
        self::assertSame('/api/image/{module}/{variant}/{image}', $match->path());
        self::assertSame('GET', $match->method());
        self::assertSame([
            'module' => 'cms.news',
            'variant' => 'news-detail',
            'image' => '42',
        ], $match->params());
    }
}
