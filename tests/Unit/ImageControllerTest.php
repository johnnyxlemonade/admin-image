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
use Lemonade\Image\ImageVariantRegistry;
use Nyholm\Psr7\Factory\Psr17Factory;
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

        self::assertSame(404, $controller->show('system.users', 'unknown', '123')->getStatusCode());
        self::assertSame(404, $controller->show('wrong.module', 'card', '123')->getStatusCode());
        self::assertSame(404, $controller->show('system.users', 'card', 'missing')->getStatusCode());
    }

    public function testItStreamsFrameworkGeneratedVariantWithCorrectMimeAndCacheHit(): void
    {
        [$controller, $renderer] = $this->controllerWithRenderer();

        $first = $controller->show('system.users', 'card', '123');
        $second = $controller->show('system.users', 'card', '123');

        self::assertSame(200, $first->getStatusCode());
        self::assertSame('image/webp', $first->getHeaderLine('Content-Type'));
        self::assertSame('nosniff', $first->getHeaderLine('X-Content-Type-Options'));
        self::assertSame('variant', (string) $first->getBody());
        self::assertSame(200, $second->getStatusCode());
        self::assertSame(1, $renderer->calls);
    }

    private function controller(): ImageController
    {
        return $this->controllerWithRenderer()[0];
    }

    /**
     * @return array{ImageController, RecordingControllerVariantRenderer}
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
                $context,
                new Responses(new ResponseBuilder($factory, $factory)),
            ),
            $renderer,
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
