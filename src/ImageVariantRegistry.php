<?php

declare(strict_types=1);

namespace Lemonade\Image;

use Lemonade\Framework\Image\Value\ImageVariantDefinition;

/**
 * Eviduje pojmenovane Framework varianty, ktere moduly povoluji pro verejne image URL
 */
final class ImageVariantRegistry
{
    /**
     * @var array<string, ImageVariantDefinition>
     */
    private array $definitions = [];

    /**
     * Registruje jedinecnou shared presentation variantu
     */
    public function register(string $variant, ImageVariantDefinition $definition): void
    {
        $this->assertVariant($variant);
        if (isset($this->definitions[$variant])) {
            throw new \LogicException(sprintf('Image variant "%s" is already registered.', $variant));
        }

        $this->definitions[$variant] = $definition;
    }

    /**
     * Vraci variantu nebo null kdyz modul ani variantu nezaregistroval
     */
    public function get(string $variant): ?ImageVariantDefinition
    {
        return $this->definitions[$variant] ?? null;
    }

    /**
     * Vraci povinnou variantu pro generator URL a hlasi nezaregistrovanou dvojici
     */
    public function require(string $variant): ImageVariantDefinition
    {
        $definition = $this->get($variant);
        if ($definition === null) {
            throw new \OutOfBoundsException(sprintf('Image variant "%s" is not registered.', $variant));
        }

        return $definition;
    }

    /**
     * Overuje kratke jmeno varianty bez URL nebo filesystem separatoru
     */
    private function assertVariant(string $variant): void
    {
        if (preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $variant) !== 1) {
            throw new \InvalidArgumentException('Image variant code is invalid.');
        }
    }
}
