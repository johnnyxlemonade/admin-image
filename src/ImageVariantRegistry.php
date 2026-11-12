<?php

declare(strict_types=1);

namespace Lemonade\Image;

use Lemonade\Framework\Image\Value\ImageVariantDefinition;

/**
 * Stores named Framework variants available through public image URLs
 */
final class ImageVariantRegistry
{
    /**
     * @var array<string, ImageVariantDefinition>
     */
    private array $definitions = [];

    /**
     * Registers a named variant and rejects invalid or duplicate names
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
     * Returns the registered variant or null when the name is unknown
     */
    public function get(string $variant): ?ImageVariantDefinition
    {
        return $this->definitions[$variant] ?? null;
    }

    /**
     * Returns the registered variant or throws when the name is unknown
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
     * Validates a short variant name without URL or filesystem separators
     */
    private function assertVariant(string $variant): void
    {
        if (preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $variant) !== 1) {
            throw new \InvalidArgumentException('Image variant code is invalid.');
        }
    }
}
