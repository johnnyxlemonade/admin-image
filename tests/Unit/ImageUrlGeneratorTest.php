<?php

declare(strict_types=1);

namespace Lemonade\Image\Tests\Unit;

use Lemonade\Framework\Image\Value\ImageBackground;
use Lemonade\Framework\Image\Value\ImageDimensions;
use Lemonade\Framework\Image\Value\ImageFormat;
use Lemonade\Framework\Image\Value\ImageQuality;
use Lemonade\Framework\Image\Value\ImageVariantDefinition;
use Lemonade\Framework\Routing\Router;
use Lemonade\Framework\Routing\UrlGenerator;
use Lemonade\Image\ImageIdentifier;
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
        (new ImageRouteRegistrar())->registerRoutes($router);
        $generator = new ImageUrlGenerator(new UrlGenerator($router), $registry);

        self::assertSame('/api/image/cms.news/card/novinka-123', $generator->url('cms.news', 'card', 'novinka-123'));
    }

    public function testItRejectsUnsafeIdentifierAndUnknownVariant(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ImageIdentifier::fromString('bad/path');
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
}
