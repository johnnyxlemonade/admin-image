<?php

declare(strict_types=1);

namespace Lemonade\Image\Tests\Unit;

use Lemonade\Framework\Core\Context\ApplicationContext;
use Lemonade\Framework\Core\Context\DebugMode;
use Lemonade\Framework\Core\Context\Environment;
use Lemonade\Framework\Core\Context\Path;
use Lemonade\Framework\Filesystem\DirectoryPathGenerator;
use Lemonade\Framework\Filesystem\Filesystem;
use Lemonade\Framework\Filesystem\Manager\DirectoryManager;
use Lemonade\Framework\Filesystem\Manager\FileManager;
use Lemonade\Framework\Filesystem\Manager\LockManager;
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
use PHPUnit\Framework\TestCase;

final class ImageVariantFlowTest extends TestCase
{
    private string $root = '';

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/lemonade-image-flow-' . bin2hex(random_bytes(8));
        mkdir($this->root . '/storage/images/originals', 0775, true);
        mkdir($this->root . '/public', 0775, true);
    }

    protected function tearDown(): void
    {
        $this->remove($this->root);
    }

    public function testItDelegatesGenerationAndCacheHitsToFrameworkImageFlow(): void
    {
        $context = new ApplicationContext(
            Environment::Testing,
            new Path($this->root, $this->root . '/public'),
            DebugMode::disabled(),
        );
        $paths = new ImageVariantPathResolver($context, new DirectoryPathGenerator());
        $asset = new ImageAsset(
            'asset-123',
            $paths->originalReference(
                'asset-123',
                'version123',
                \Lemonade\Framework\Image\Value\ImageOriginalStorage::Storage,
                ImageFormat::Png,
                new ImageDimensions(1, 1),
            ),
        );
        $cache = new ImageVariantCache(
            new Filesystem(new DirectoryManager(), new FileManager(), new LockManager(new DirectoryManager())),
            $paths,
        );
        $renderer = new RecordingImageVariantRenderer();
        $generator = new ImageVariantGenerator($cache, $paths, $renderer);
        $definition = new ImageVariantDefinition(
            new ImageDimensions(320, 180),
            ImageFormat::Webp,
            ImageQuality::fromInt(82),
            ImageBackground::color('#ffffff'),
        );

        $first = $generator->generate($asset, $definition);
        $second = $generator->generate($asset, $definition);

        self::assertSame(1, $renderer->calls);
        self::assertSame($first->publicPath(), $second->publicPath());
        self::assertFileExists($context->uploadPath($first->publicPath()));
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

final class RecordingImageVariantRenderer implements ImageVariantRendererInterface
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
