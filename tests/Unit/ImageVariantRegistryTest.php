<?php

declare(strict_types=1);

namespace Lemonade\Image\Tests\Unit;

use Lemonade\Framework\Image\Value\ImageBackground;
use Lemonade\Framework\Image\Value\ImageDimensions;
use Lemonade\Framework\Image\Value\ImageFormat;
use Lemonade\Framework\Image\Value\ImageQuality;
use Lemonade\Framework\Image\Value\ImageVariantDefinition;
use Lemonade\Image\ImageVariantRegistry;
use PHPUnit\Framework\TestCase;

final class ImageVariantRegistryTest extends TestCase
{
    public function testItRegistersAndReturnsSharedVariant(): void
    {
        $registry = new ImageVariantRegistry();
        $definition = $this->definition();
        $registry->register('card', $definition);

        self::assertSame($definition, $registry->get('card'));
        self::assertSame($definition, $registry->require('card'));
        self::assertNull($registry->get('detail'));
    }

    public function testItRejectsDuplicateAndNonCanonicalRegistrations(): void
    {
        $registry = new ImageVariantRegistry();
        $registry->register('card', $this->definition());

        $this->expectException(\LogicException::class);
        $registry->register('card', $this->definition());
    }

    public function testItRejectsInvalidVariantCodes(): void
    {
        $registry = new ImageVariantRegistry();

        $this->expectException(\InvalidArgumentException::class);
        $registry->register('card/invalid', $this->definition());
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
