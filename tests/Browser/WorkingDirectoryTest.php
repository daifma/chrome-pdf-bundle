<?php

namespace Daif\ChromePdfBundle\Tests\Browser;

use Daif\ChromePdfBundle\Browser\WorkingDirectory;
use Daif\ChromePdfBundle\Exception\BrowserException;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\Filesystem\Filesystem;

final class WorkingDirectoryTest extends TestCase
{
    private const ASSETS = __DIR__.'/../Fixtures/assets';

    private string $base;

    protected function setUp(): void
    {
        $this->base = sys_get_temp_dir().\DIRECTORY_SEPARATOR.'chrome_pdf_wd_test_'.bin2hex(random_bytes(4));
        (new Filesystem())->mkdir($this->base);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->base);
    }

    public function testCreateWriteCopyAndRemove(): void
    {
        $directory = WorkingDirectory::create($this->base);
        $path = $directory->getPath();

        self::assertDirectoryExists($path);
        self::assertStringStartsWith('chrome_pdf_', basename($path));
        self::assertSame(realpath($this->base), \dirname($path));

        $index = $directory->writeFile('index.html', '<p>x</p>');
        self::assertStringEqualsFile($index, '<p>x</p>');

        $directory->copyAssets([
            'logo.png' => new \SplFileInfo(self::ASSETS.'/logo.png'),
            'C:\project\assets\pdf\document.pdf' => new \SplFileInfo(self::ASSETS.'/pdf/document.pdf'),
        ]);
        self::assertFileEquals(self::ASSETS.'/logo.png', $path.'/logo.png');
        self::assertFileEquals(self::ASSETS.'/pdf/document.pdf', $path.'/document.pdf');

        $directory->remove();
        self::assertDirectoryDoesNotExist($path);
    }

    public function testSameAssetTwiceIsAccepted(): void
    {
        $directory = WorkingDirectory::create($this->base);
        $directory->copyAssets([
            'logo.png' => new \SplFileInfo(self::ASSETS.'/logo.png'),
            self::ASSETS.'/logo.png' => new \SplFileInfo(self::ASSETS.'/logo.png'),
        ]);

        self::assertFileExists($directory->getPath().'/logo.png');
    }

    public function testDuplicateBasenameFromDifferentSourcesIsRejected(): void
    {
        $directory = WorkingDirectory::create($this->base);

        $this->expectException(BrowserException::class);
        $this->expectExceptionMessage('Two different assets share the same file name "document.pdf"');

        $directory->copyAssets([
            'pdf/document.pdf' => new \SplFileInfo(self::ASSETS.'/pdf/document.pdf'),
            'other/document.pdf' => new \SplFileInfo(self::ASSETS.'/pdf/other_document.pdf'),
        ]);
    }

    public function testMissingAssetIsRejected(): void
    {
        $directory = WorkingDirectory::create($this->base);

        $this->expectException(BrowserException::class);

        $directory->copyAssets(['missing.png' => new \SplFileInfo(self::ASSETS.'/missing.png')]);
    }

    public function testRemoveNeverThrows(): void
    {
        $directory = WorkingDirectory::create($this->base);
        $directory->remove();

        $logger = new class extends AbstractLogger {
            /** @var list<string> */
            public array $logs = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->logs[] = (string) $message;
            }
        };

        $directory->remove($logger);
        self::assertSame([], $logger->logs);
    }
}
