<?php

declare(strict_types=1);

namespace Lemonade\Image\Tests\Unit;

use Lemonade\Framework\Api\Endpoint\ApiEndpointRegistrar;
use Lemonade\Framework\Api\Endpoint\ApiEndpointRegistry;
use Lemonade\Framework\Image\Value\ImageBackground;
use Lemonade\Framework\Image\Value\ImageDimensions;
use Lemonade\Framework\Image\Value\ImageFormat;
use Lemonade\Framework\Image\Value\ImageQuality;
use Lemonade\Framework\Image\Value\ImageVariantDefinition;
use Lemonade\Framework\Routing\Router;
use Lemonade\Framework\Routing\UrlGenerator;
use Lemonade\Image\ImageUrlGenerator;
use Lemonade\Image\ImageVariantRegistry;
use Lemonade\Image\Routing\ImageRouteRegistrar;
use PHPUnit\Framework\TestCase;

final class ImageUrlGeneratorTest extends TestCase
{
    public function testItBuildsCanonicalUrlWithoutOutputExtension(): void
    {
        $registry = new ImageVariantRegistry();
        $registry->register('card', $this->definition());
        $router = new Router();
        $this->registerImageRoute($router);
        $generator = new ImageUrlGenerator(new UrlGenerator($router), $registry);

        self::assertSame('/api/image/card/123', $generator->url('card', 123));
    }

    public function testItBuildsCanonicalOriginalUrlWithoutVariantDefinition(): void
    {
        $router = new Router();
        $this->registerImageRoute($router);
        $generator = new ImageUrlGenerator(new UrlGenerator($router), new ImageVariantRegistry());

        self::assertSame('/api/image/original/123', $generator->originalUrl(123));
    }

    public function testItRejectsNonPositiveFileId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $generator = new ImageUrlGenerator(new UrlGenerator(new Router()), new ImageVariantRegistry());
        $generator->originalUrl(0);
    }

    private function definition(): ImageVariantDefinition
    {
        return new ImageVariantDefinition(
            new ImageDimensions(320, 180),
            ImageFormat::Webp,
            ImageQuality::fromInt(82),
            ImageBackground::color('#ffffff'),
        );
    }

    private function registerImageRoute(Router $router): void
    {
        $endpoints = new ApiEndpointRegistry();
        (new ImageRouteRegistrar())->register($endpoints);
        (new ApiEndpointRegistrar($router, $endpoints))->registerRoutes('/api');
    }
}
