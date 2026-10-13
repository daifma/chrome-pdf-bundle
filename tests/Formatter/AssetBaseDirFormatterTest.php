<?php

namespace Daif\ChromePdfBundle\Tests\Formatter;

use Daif\ChromePdfBundle\Formatter\AssetBaseDirFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\Filesystem\Path;

final class AssetBaseDirFormatterTest extends TestCase
{
    private const PROJECT_DIR = __DIR__.'/../';

    /**
     * @return iterable<string, array<int, list<string>|string>>
     */
    public static function generateBaseDirectoryAndPath(): iterable
    {
        $projectDir = \dirname(self::PROJECT_DIR, 2);

        yield 'absolute path and absolute base dir' => [$projectDir.'/Fixtures/assets/file.md', [$projectDir.'/Fixtures/assets'], $projectDir.'/Fixtures/assets/file.md'];
        yield 'absolute path and relative base dir' => [$projectDir.'/Fixtures/assets/file.md', ['assets'], $projectDir.'/Fixtures/assets/file.md'];
        yield 'relative path and relative base dir' => ['file.md', ['Fixtures/assets'], Path::canonicalize($projectDir.'/Fixtures/assets/file.md')];
        yield 'relative path and absolute base dir' => ['logo.png', [$projectDir.'/Fixtures/assets'], Path::canonicalize($projectDir.'/Fixtures/assets/logo.png')];
        yield 'relative path and relative base dir with end slash' => ['file.md', ['Fixtures/assets/'], Path::canonicalize($projectDir.'/Fixtures/assets/file.md')];
        yield 'URL path and absolute base dir' => ['https://example.com/assets/images/logo.png', [$projectDir.'/Fixtures/assets'], 'https://example.com/assets/images/logo.png'];
        yield 'URL path and relative base dir' => ['https://example.com/assets/images/logo.png', ['assets'], 'https://example.com/assets/images/logo.png'];
        yield 'absolute path and two absolute base dir' => [$projectDir.'/Fixtures/assets/logo.png', [$projectDir.'/Fixtures/assets', $projectDir.'/Fixtures/files'], $projectDir.'/Fixtures/assets/logo.png'];
        yield 'absolute path and two relative base dir' => [$projectDir.'/Fixtures/assets/logo.png', ['Fixtures/assets', 'Fixtures/files'], $projectDir.'/Fixtures/assets/logo.png'];
        yield 'relative path and two absolute base dir' => ['logo.png', [$projectDir.'/Fixtures/files', $projectDir.'/Fixtures/assets'], Path::canonicalize($projectDir.'/Fixtures/assets/logo.png')];
        yield 'relative path and two relative base dir' => ['logo.png', ['Fixtures/files', 'Fixtures/assets'], Path::canonicalize($projectDir.'/Fixtures/assets/logo.png')];
        yield 'relative windows path' => ['pdf\\document.pdf', ['Fixtures/assets'], Path::canonicalize($projectDir.'/Fixtures/assets/pdf/document.pdf')];
        yield 'relative windows base dir' => ['document.pdf', ['Fixtures\\assets\\pdf\\'], Path::canonicalize($projectDir.'/Fixtures/assets/pdf/document.pdf')];
        yield 'windows absolute path' => ['C:\\assets\\logo.png', ['Fixtures/assets'], 'C:\\assets\\logo.png'];
        yield 'windows absolute path with forward slashes' => ['C:/assets/logo.png', ['Fixtures/assets'], 'C:/assets/logo.png'];
        yield 'windows unc path' => ['\\\\server\\share\\logo.png', ['Fixtures/assets'], '\\\\server\\share\\logo.png'];
    }

    /**
     * @param string[] $baseDirectories
     */
    #[DataProvider('generateBaseDirectoryAndPath')]
    #[TestDox('Resolve path when "$_dataName"')]
    public function testResolvePathCorrectly(string $path, array $baseDirectories, string $expectedResult): void
    {
        $assetBaseDirFormatter = new AssetBaseDirFormatter(self::PROJECT_DIR, $baseDirectories);
        $resolvedPath = $assetBaseDirFormatter->resolve($path);
        self::assertSame($expectedResult, $resolvedPath);
    }

    public function testMissingFileThrows(): void
    {
        $this->expectException(FileNotFoundException::class);

        (new AssetBaseDirFormatter(self::PROJECT_DIR, ['Fixtures/assets']))->resolve('missing\\file.png');
    }
}
