<?php

declare(strict_types=1);

namespace Lemonade\Image;

/**
 * Overuje stabilni domenovy identifikator obrazku pouzity v URL a storage namespace
 */
final readonly class ImageIdentifier
{
    /**
     * Nastavuje overenou hodnotu identifikatoru bez filesystemove syntaxe
     */
    private function __construct(private string $value) {}

    /**
     * Vytvari identifikator z domenove hodnoty nebo odmita nekanonickou hodnotu
     */
    public static function fromString(string $value): self
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,127}$/', $value) !== 1) {
            throw new \InvalidArgumentException('Image identifier is invalid.');
        }

        return new self($value);
    }

    /**
     * Vraci kanonickou hodnotu domenove identity obrazku
     */
    public function value(): string
    {
        return $this->value;
    }
}
