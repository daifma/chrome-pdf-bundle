<?php

namespace Daif\ChromePdfBundle\Tests\Formatter;

use Daif\ChromePdfBundle\Formatter\PathHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PathHelperTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function paths(): iterable
    {
        yield 'posix absolute' => ['/var/www/assets/logo.png', 'logo.png', 'png'];
        yield 'windows absolute' => ['C:\\Users\\me\\assets\\logo.PNG', 'logo.PNG', 'png'];
        yield 'windows forward slashes' => ['C:/Users/me/assets/logo.png', 'logo.png', 'png'];
        yield 'windows unc' => ['\\\\server\\share\\fonts\\font.woff2', 'font.woff2', 'woff2'];
        yield 'mixed separators' => ['assets\\img/photo.jpeg', 'photo.jpeg', 'jpeg'];
        yield 'relative' => ['file.md', 'file.md', 'md'];
        yield 'trailing separator' => ['assets\\img\\', 'img', ''];
        yield 'url with query and fragment' => ['https://cdn.example.com/a/b%20c.png?v=1#top', 'b c.png', 'png'];
        yield 'no extension' => ['C:\\tmp\\README', 'README', ''];
        yield 'dot file' => ['/tmp/.env', '.env', 'env'];
    }

    #[DataProvider('paths')]
    public function testBasenameAndExtension(string $path, string $basename, string $extension): void
    {
        self::assertSame($basename, PathHelper::basename($path));
        self::assertSame($extension, PathHelper::extension($path));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function absolutePaths(): iterable
    {
        yield 'posix' => ['/var/www', true];
        yield 'windows drive backslash' => ['C:\\Users', true];
        yield 'windows drive slash' => ['d:/data', true];
        yield 'windows drive only' => ['C:', true];
        yield 'unc' => ['\\\\server\\share', true];
        yield 'url' => ['https://example.com/a.png', true];
        yield 'stream wrapper' => ['phar://archive.phar/file', true];
        yield 'relative' => ['assets/logo.png', false];
        yield 'relative windows' => ['assets\\logo.png', false];
        yield 'drive-like relative' => ['C:file.png', false];
        yield 'empty' => ['', false];
    }

    #[DataProvider('absolutePaths')]
    public function testIsAbsolute(string $path, bool $expected): void
    {
        self::assertSame($expected, PathHelper::isAbsolute($path));
    }
}
