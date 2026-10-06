<?php

declare(strict_types=1);

namespace Lemonade\Image\Contract;

use Lemonade\Framework\Image\Value\ImageAsset;
use Lemonade\Image\ImageIdentifier;

/**
 * Prevadi verejnou modulovou image identity na aktivni Framework asset bez znalosti persistence
 */
interface ImageAssetResolverInterface
{
    /**
     * Vraci aktivni Framework asset nebo null kdyz image identity nelze verejne dorucit
     */
    public function resolve(string $module, ImageIdentifier $identifier): ?ImageAsset;
}
