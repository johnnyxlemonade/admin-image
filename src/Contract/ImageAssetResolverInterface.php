<?php

declare(strict_types=1);

namespace Lemonade\Image\Contract;

use Lemonade\Framework\Image\Value\ImageAsset;

/**
 * Resolves global file IDs to Framework image assets without coupling delivery to persistence
 */
interface ImageAssetResolverInterface
{
    /**
     * Returns an available image asset or null when the file cannot be delivered
     */
    public function resolve(int $fileId): ?ImageAsset;
}
