<?php

declare(strict_types=1);

namespace Lemonade\Image\Tests\Unit;

use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use Lemonade\Framework\Core\Http\ResponseBuilder;
use Lemonade\Framework\Filesystem\DirectoryPathGenerator;
use Lemonade\Framework\Filesystem\Filesystem;
use Lemonade\Framework\Filesystem\Manager\DirectoryManager;
use Lemonade\Framework\Filesystem\Manager\FileManager;
use Lemonade\Framework\Filesystem\Manager\LockManager;
use Lemonade\Framework\Http\Response\Responses;
use Lemonade\Framework\Image\Contract\ImageVariantRendererInterface;
use Lemonade\Framework\Image\ImageVariantCache;
use Lemonade\Framework\Image\ImageVariantGenerator;
use Lemonade\Framework\Image\ImageVariantPathResolver;
use Lemonade\Framework\Image\Value\EncodedImage;
use Lemonade\Framework\Image\Value\ImageAsset;
use Lemonade\Framework\Image\Value\ImageBackground;
use Lemonade\Framework\Image\Value\ImageDimensions;
use Lemonade\Framework\Image\Value\ImageFormat;
use Lemonade\Framework\Image\Value\ImageOriginalReference;
use Lemonade\Framework\Image\Value\ImageQuality;
use Lemonade\Framework\Image\Value\ImageSource;
use Lemonade\Framework\Image\Value\ImageVariantDefinition;
use Lemonade\Image\Contract\ImageAssetResolverInterface;
use Lemonade\Image\Http\Controller\ImageController;
use Lemonade\Image\ImageIdentifier;
use Lemonade\Image\ImageUrlGenerator;
use Lemonade\Image\ImageVariantRegistry;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;

final class ImageControllerTest extends TestCase
{
    private string $root = '';

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/lemonade-image-controller-' . bin2hex(random_bytes(8));
        mkdir($this->root . '/storage/images/originals', 0775, true);
        mkdir($this->root . '/public', 0775, true);
    }

    protected function tearDown(): void
    {
        $this->remove($this->root);
    }

    public function testItReturns404ForUnknownVariantWrongModuleAndMissingImage(): void
    {
        $controller = $this->controller();

        $request = $this->request();

        self::assertSame(404, $controller->show('system.users', 'unknown', '123', $request)->getStatusCode());
        self::assertSame(404, $controller->show('wrong.module', 'card', '123', $request)->getStatusCode());
        self::assertSame(404, $controller->show('system.users', 'card', 'missing', $request)->getStatusCode());
    }

    public function testItStreamsFrameworkGeneratedVariantWithCorrectMimeAndCacheHit(): void
    {
        [$controller, $renderer] = $this->controllerWithRenderer();

        $first = $controller->show('system.users', 'card', '123', $this->request());
        $second = $controller->show('system.users', 'card', '123', $this->request());

        self::assertSame(200, $first->getStatusCode());
        self::assertSame('image/webp', $first->getHeaderLine('Content-Type'));
        self::assertSame('nosniff', $first->getHeaderLine('X-Content-Type-Options'));
        self::assertNotSame('', $first->getHeaderLine('ETag'));
        self::assertNotSame('', $first->getHeaderLine('Last-Modified'));
        self::assertSame('variant', (string) $first->getBody());
        self::assertSame(200, $second->getStatusCode());
        self::assertSame(1, $renderer->calls);
    }

    public function testItStreamsStoredCanonicalOriginalWithoutRenderingVariant(): void
    {
        [$controller, $renderer] = $this->storedOriginal();

        $response = $controller->show(
            'system.users',
            ImageUrlGenerator::ORIGINAL_ROUTE_VARIANT,
            '123',
            $this->request(),
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('image/png', $response->getHeaderLine('Content-Type'));
        self::assertSame('original', (string) $response->getBody());
        self::assertSame(0, $renderer->calls);
    }

    public function testItReturns304ForMatchingEtagWithoutStreamingTheBody(): void
    {
        [$controller, $renderer] = $this->controllerWithRenderer();
        $first = $controller->show('system.users', 'card', '123', $this->request());

        $response = $controller->show(
            'system.users',
            'card',
            '123',
            $this->request()->withHeader('If-None-Match', $first->getHeaderLine('ETag')),
        );

        self::assertSame(304, $response->getStatusCode());
        self::assertSame('', (string) $response->getBody());
        self::assertSame('', $response->getHeaderLine('Content-Type'));
        self::assertSame('', $response->getHeaderLine('Content-Length'));
        self::assertSame('public, max-age=0, must-revalidate', $response->getHeaderLine('Cache-Control'));
        self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        self::assertSame($first->getHeaderLine('ETag'), $response->getHeaderLine('ETag'));
        self::assertSame($first->getHeaderLine('Last-Modified'), $response->getHeaderLine('Last-Modified'));
        self::assertSame(1, $renderer->calls);
    }

    public function testItReturns304ForUnchangedLastModifiedWhenEtagIsAbsent(): void
    {
        [$controller] = $this->controllerWithRenderer();
        $first = $controller->show('system.users', 'card', '123', $this->request());

        $response = $controller->show(
            'system.users',
            'card',
            '123',
            $this->request()->withHeader('If-Modified-Since', $first->getHeaderLine('Last-Modified')),
        );

        self::assertSame(304, $response->getStatusCode());
    }

    public function testIfNoneMatchTakesPrecedenceOverIfModifiedSince(): void
    {
        [$controller] = $this->controllerWithRenderer();
        $first = $controller->show('system.users', 'card', '123', $this->request());
        foreach (['"different-representation"', '', 'invalid'] as $etag) {
            $request = $this->request()
                ->withHeader('If-None-Match', $etag)
                ->withHeader('If-Modified-Since', $first->getHeaderLine('Last-Modified'));

            self::assertSame(200, $controller->show('system.users', 'card', '123', $request)->getStatusCode());
        }

        foreach (['W/' . $first->getHeaderLine('ETag'), '"other", W/' . $first->getHeaderLine('ETag'), '*'] as $etag) {
            $request = $this->request()
                ->withHeader('If-None-Match', $etag)
                ->withHeader('If-Modified-Since', 'Sun, 06 Nov 1994 08:49:37 GMT');

            self::assertSame(304, $controller->show('system.users', 'card', '123', $request)->getStatusCode());
        }
    }

    public function testChangedBytesAtSamePathInvalidateEtagEvenWithSameSizeAndMtime(): void
    {
        [$controller, $renderer, $path] = $this->storedOriginal();
        $first = $controller->show('system.users', 'original', '123', $this->request());
        $modifiedAt = filemtime($path);
        self::assertIsInt($modifiedAt);

        file_put_contents($path, 'replaced');
        touch($path, $modifiedAt);
        $request = $this->request()
            ->withHeader('If-None-Match', $first->getHeaderLine('ETag'))
            ->withHeader('If-Modified-Since', $first->getHeaderLine('Last-Modified'));
        $response = $controller->show('system.users', 'original', '123', $request);

        self::assertSame(200, $response->getStatusCode());
        self::assertNotSame($first->getHeaderLine('ETag'), $response->getHeaderLine('ETag'));
        self::assertSame('replaced', (string) $response->getBody());
        self::assertSame(0, $renderer->calls);
        $unchanged = $controller->show('system.users', 'original', '123', $this->request()->withHeader('If-None-Match', $response->getHeaderLine('ETag')));
        self::assertSame(304, $unchanged->getStatusCode());
        self::assertSame('', (string) $unchanged->getBody());
    }

    public function testInvalidHttpDatesAreIgnored(): void
    {
        [$controller] = $this->controllerWithRenderer();
        foreach (['', 'tomorrow', 'not a date', 'Wed, 31 Feb 2094 08:49:37 GMT', 'Sun, 06 Nov 2094 08:49:37 UTC', 'Sun, 06 Nov 1994 08:49:37 GMT, Sun, 06 Nov 1994 08:49:37 GMT'] as $date) {
            $response = $controller->show('system.users', 'card', '123', $this->request()->withHeader('If-Modified-Since', $date));
            self::assertSame(200, $response->getStatusCode(), $date);
        }
    }

    public function testAllHttpDateFormatsAreAccepted(): void
    {
        [$controller, , $path] = $this->storedOriginal();
        touch($path, 784111777);
        foreach (['Sun, 06 Nov 1994 08:49:37 GMT', 'Sunday, 06-Nov-94 08:49:37 GMT', 'Sun Nov  6 08:49:37 1994'] as $date) {
            $response = $controller->show('system.users', 'original', '123', $this->request()->withHeader('If-Modified-Since', $date));
            self::assertSame(304, $response->getStatusCode(), $date);
        }
        $older = $controller->show('system.users', 'original', '123', $this->request()->withHeader('If-Modified-Since', 'Sun, 06 Nov 1994 08:49:36 GMT'));
        self::assertSame(200, $older->getStatusCode());
    }

    public function testFutureMtimeIsClampedForHeadersAndConditionalComparison(): void
    {
        [$controller, , $path] = $this->storedOriginal();
        touch($path, time() + 86400);
        $before = time();
        $request = $this->request()->withHeader('If-Modified-Since', gmdate('D, d M Y H:i:s', $before + 60) . ' GMT');
        $response = $controller->show('system.users', 'original', '123', $request);
        $modifiedAt = \DateTimeImmutable::createFromFormat('D, d M Y H:i:s T', $response->getHeaderLine('Last-Modified'));

        self::assertInstanceOf(\DateTimeImmutable::class, $modifiedAt);
        self::assertGreaterThanOrEqual($before, $modifiedAt->getTimestamp());
        self::assertLessThanOrEqual(time(), $modifiedAt->getTimestamp());
        self::assertSame(304, $response->getStatusCode());
        self::assertSame('', (string) $response->getBody());
    }

    /**
     * @return array{ImageController, RecordingControllerVariantRenderer, string}
     */
    private function storedOriginal(): array
    {
        [$controller, $renderer, $paths] = $this->controllerWithRenderer();
        $asset = (new RecordingAssetResolver($paths))->resolve('system.users', ImageIdentifier::fromString('123'));
        self::assertInstanceOf(ImageAsset::class, $asset);
        $path = $paths->originalPath($asset);
        mkdir(dirname($path), 0775, true);
        file_put_contents($path, 'original');

        return [$controller, $renderer, $path];
    }

    private function request(): ServerRequest
    {
        return new ServerRequest('GET', '/api/image/system.users/card/123');
    }

    private function controller(): ImageController
    {
        return $this->controllerWithRenderer()[0];
    }

    /**
     * @return array{ImageController, RecordingControllerVariantRenderer, ImageVariantPathResolver}
     */
    private function controllerWithRenderer(): array
    {
        $context = new ApplicationContext(
            Environment::Testing,
            new Path($this->root, $this->root . '/public'),
            DebugMode::disabled(),
        );
        $registry = new ImageVariantRegistry();
        $registry->register('card', new ImageVariantDefinition(
            new ImageDimensions(320, 180),
            ImageFormat::Webp,
            ImageQuality::fromInt(82),
            ImageBackground::color('#ffffff'),
        ));
        $paths = new ImageVariantPathResolver($context, new DirectoryPathGenerator());
        $filesystem = new Filesystem(
            new DirectoryManager(),
            new FileManager(),
            new LockManager(new DirectoryManager()),
        );
        $renderer = new RecordingControllerVariantRenderer();
        $generator = new ImageVariantGenerator(
            new ImageVariantCache($filesystem, $paths),
            $paths,
            $renderer,
        );
        $factory = new Psr17Factory();

        return [
            new ImageController(
                $registry,
                new RecordingAssetResolver($paths),
                $generator,
                $paths,
                $context,
                new Responses(new ResponseBuilder($factory, $factory)),
            ),
            $renderer,
            $paths,
        ];
    }

    private function remove(string $path): void
    {
        if (is_file($path) || is_link($path)) {
            unlink($path);

            return;
        }

        $entries = scandir($path);
        foreach ($entries === false ? [] : $entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $this->remove($path . '/' . $entry);
        }

        rmdir($path);
    }
}

final class RecordingControllerVariantRenderer implements ImageVariantRendererInterface
{
    public int $calls = 0;

    public function render(
        ImageSource $source,
        ImageOriginalReference $original,
        ImageVariantDefinition $definition,
    ): EncodedImage {
        $this->calls++;

        return new EncodedImage('variant', $definition->format(), $definition->dimensions());
    }
}

/**
 * Poskytuje jednu aktivni DB-like image identity bez filesystem lookupu
 */
final readonly class RecordingAssetResolver implements ImageAssetResolverInterface
{
    /**
     * Nastavuje Framework resolver canonical original reference
     */
    public function __construct(private ImageVariantPathResolver $paths) {}

    public function resolve(string $module, ImageIdentifier $identifier): ?ImageAsset
    {
        if ($module !== 'system.users' || $identifier->value() !== '123') {
            return null;
        }

        return new ImageAsset(
            'asset-123',
            $this->paths->originalReference(
                'asset-123',
                'version123',
                \Lemonade\Framework\Image\Value\ImageOriginalStorage::Storage,
                ImageFormat::Png,
                new ImageDimensions(1, 1),
            ),
        );
    }
}
