<?php

declare(strict_types=1);

namespace Lemonade\Image\Http\Controller;

use DateTimeImmutable;
use DateTimeZone;
use Generator;
use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Http\HttpStatus;
use Lemonade\Framework\Http\Response\Responses;
use Lemonade\Framework\Image\Exception\ImageException;
use Lemonade\Framework\Image\ImageVariantGenerator;
use Lemonade\Framework\Image\ImageVariantPathResolver;
use Lemonade\Framework\Image\Value\ImageAsset;
use Lemonade\Image\Contract\ImageAssetResolverInterface;
use Lemonade\Image\ImageUrlGenerator;
use Lemonade\Image\ImageVariantRegistry;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Delivers image variants and originals with content-based HTTP cache validation
 */
final readonly class ImageController
{
    /**
     * Connects source resolution, variant generation, and HTTP delivery
     */
    public function __construct(
        private ImageVariantRegistry $variants,
        private ImageAssetResolverInterface $sources,
        private ImageVariantGenerator $generator,
        private ImageVariantPathResolver $paths,
        private ApplicationContext $context,
        private Responses $responses,
    ) {}

    /**
     * Streams an available image or returns 304 for a matching cache validator, otherwise 404
     */
    public function show(
        string $variant,
        string $fileId,
        ServerRequestInterface $request,
    ): ResponseInterface {
        $id = filter_var($fileId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false || (string) $id !== $fileId) {
            return $this->notFound();
        }

        $asset = $this->sources->resolve($id);
        if ($asset === null) {
            return $this->notFound();
        }

        if ($variant === ImageUrlGenerator::ORIGINAL_ROUTE_VARIANT) {
            return $this->original($asset, $request);
        }

        $definition = $this->variants->get($variant);
        if ($definition === null) {
            return $this->notFound();
        }

        try {
            $reference = $this->generator->generate($asset, $definition);
        } catch (ImageException) {
            return $this->notFound();
        }

        return $this->responseForPath(
            path: $this->context->uploadPath($reference->publicPath()),
            contentType: $reference->format()->mimeType(),
            request: $request,
        );
    }

    private function original(ImageAsset $asset, ServerRequestInterface $request): ResponseInterface
    {
        return $this->responseForPath(
            path: $this->paths->originalPath($asset),
            contentType: $asset->original()->format()->mimeType(),
            request: $request,
        );
    }

    private function responseForPath(
        string $path,
        string $contentType,
        ServerRequestInterface $request,
    ): ResponseInterface {
        clearstatcache();
        $size = @filesize($path);
        $modifiedAt = @filemtime($path);
        if (!is_int($size) || $size < 1 || !is_int($modifiedAt)) {
            return $this->notFound();
        }

        $hash = @hash_file('sha256', $path);
        if ($hash === false) {
            return $this->notFound();
        }

        $etag = '"' . $hash . '"';
        $modifiedAt = min($modifiedAt, time());
        $cacheHeaders = [
            'Cache-Control' => 'public, max-age=0, must-revalidate',
            'ETag' => $etag,
            'Last-Modified' => gmdate('D, d M Y H:i:s', $modifiedAt) . ' GMT',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ($this->isNotModified($request, $etag, $modifiedAt)) {
            $response = $this->responses
                ->text('', HttpStatus::NOT_MODIFIED->value)
                ->withoutHeader('Content-Type');
        } else {
            $response = $this->responses->stream(
                producer: fn(): Generator => $this->stream($path),
                contentType: $contentType,
                headers: ['Content-Length' => (string) $size],
            );
        }

        foreach ($cacheHeaders as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }

    private function isNotModified(ServerRequestInterface $request, string $etag, int $modifiedAt): bool
    {
        // Even an empty If-None-Match takes precedence over If-Modified-Since.
        if ($request->hasHeader('If-None-Match')) {
            return $this->matchesEtag($request->getHeaderLine('If-None-Match'), $etag);
        }

        $modifiedSince = $this->httpDateTimestamp(trim($request->getHeaderLine('If-Modified-Since')));

        return $modifiedSince !== null && $modifiedAt <= $modifiedSince;
    }

    private function matchesEtag(string $header, string $etag): bool
    {
        foreach (explode(',', $header) as $candidate) {
            if (in_array(trim($candidate), ['*', $etag, 'W/' . $etag], true)) {
                return true;
            }
        }

        return false;
    }

    private function httpDateTimestamp(string $value): ?int
    {
        $parts = explode(' ', $value, 2);
        if (count($parts) !== 2) {
            return null;
        }

        $timezone = new DateTimeZone('GMT');
        $formats = [
            'D, ' => 'd M Y H:i:s \G\M\T',
            'l, ' => 'd-M-y H:i:s \G\M\T',
            'D ' => 'M d H:i:s Y',
        ];

        foreach ($formats as $weekdayFormat => $dateFormat) {
            // Validate the weekday by round-tripping; parsing it could shift the date.
            $date = DateTimeImmutable::createFromFormat('!' . $dateFormat, $parts[1], $timezone);
            if ($date === false || DateTimeImmutable::getLastErrors() !== false) {
                continue;
            }

            if ($weekdayFormat === 'l, ') {
                $date = $this->resolveRfc850Year($date);
            }

            $formatted = $date->format($weekdayFormat . $dateFormat);
            if ($weekdayFormat === 'D ') {
                $day = str_pad($date->format('j'), 2, ' ', STR_PAD_LEFT);
                $formatted = $date->format('D M ') . $day . $date->format(' H:i:s Y');
            }

            if ($formatted === $value) {
                return $date->getTimestamp();
            }
        }

        return null;
    }

    private function resolveRfc850Year(DateTimeImmutable $date): DateTimeImmutable
    {
        // RFC 850: use the latest matching year no more than 50 years in the future.
        $limit = (new DateTimeImmutable('now', $date->getTimezone()))->modify('+50 years');
        $year = intdiv((int) $limit->format('Y'), 100) * 100 + (int) $date->format('y');
        $date = $date->setDate($year, (int) $date->format('m'), (int) $date->format('d'));

        return $date > $limit ? $date->modify('-100 years') : $date;
    }

    private function notFound(): ResponseInterface
    {
        return $this->responses->text('', HttpStatus::NOT_FOUND->value);
    }

    /**
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
