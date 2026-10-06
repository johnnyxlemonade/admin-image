<?php

declare(strict_types=1);

namespace Lemonade\Image\Http\Controller;

use Generator;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Http\HttpStatus;
use Lemonade\Framework\Http\Response\Responses;
use Lemonade\Framework\Image\Exception\ImageException;
use Lemonade\Framework\Image\ImageVariantGenerator;
use Lemonade\Image\Contract\ImageAssetResolverInterface;
use Lemonade\Image\ImageIdentifier;
use Lemonade\Image\ImageVariantRegistry;
use Psr\Http\Message\ResponseInterface;

/**
 * Dodejva registrovane modulove image varianty pres canonical HTTP URL bez odhaleni originalu
 */
final class ImageController
{
    /**
     * Nastavuje registry variant, zdrojovy resolver, Framework generator a streamed HTTP odpovedi
     */
    public function __construct(
        private readonly ImageVariantRegistry $variants,
        private readonly ImageAssetResolverInterface $sources,
        private readonly ImageVariantGenerator $generator,
        private readonly ApplicationContext $context,
        private readonly Responses $responses,
    ) {}

    /**
     * Vraci cached nebo nove vytvorenou variantu, jinak prazdnou 404 odpoved
     */
    public function show(string $module, string $variant, string $image): ResponseInterface
    {
        $definition = $this->variants->get($variant);
        if ($definition === null) {
            return $this->notFound();
        }

        try {
            $identifier = ImageIdentifier::fromString($image);
        } catch (\InvalidArgumentException) {
            return $this->notFound();
        }

        $asset = $this->sources->resolve($module, $identifier);
        if ($asset === null) {
            return $this->notFound();
        }

        try {
            $reference = $this->generator->generate($asset, $definition);
        } catch (ImageException) {
            return $this->notFound();
        }

        $path = $this->context->uploadPath($reference->publicPath());
        $size = @filesize($path);
        if (!is_int($size) || $size < 1) {
            return $this->notFound();
        }

        return $this->responses->stream(
            producer: fn(): Generator => $this->stream($path),
            contentType: $reference->format()->mimeType(),
            headers: [
                'Cache-Control' => 'public, max-age=0, must-revalidate',
                'Content-Length' => (string) $size,
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    /**
     * Vraci prazdnou 404 odpoved bez informace o module storage stavu
     */
    private function notFound(): ResponseInterface
    {
        return $this->responses->text('', HttpStatus::NOT_FOUND->value);
    }

    /**
     * Cte variantu po blocich bez nacteni celeho cached souboru do pameti
     *
     * @return Generator<int, string>
     */
    private function stream(string $path): Generator
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return;
        }

        try {
            while (!feof($handle)) {
                $chunk = fread($handle, 65_536);
                if ($chunk === false || $chunk === '') {
                    break;
                }

                yield $chunk;
            }
        } finally {
            fclose($handle);
        }
    }
}
