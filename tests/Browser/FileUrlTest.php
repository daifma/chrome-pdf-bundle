<?php

namespace Daif\ChromePdfBundle\Tests\Browser;

use Daif\ChromePdfBundle\Browser\FileUrl;
use Daif\ChromePdfBundle\Exception\BrowserException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FileUrlTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providePaths(): iterable
    {
        yield 'posix' => ['/var/app/chrome_pdf_1/index.html', 'file:///var/app/chrome_pdf_1/index.html'];
        yield 'posix with spaces and unicode' => ['/var/a b/é#?.html', 'file:///var/a%20b/%C3%A9%23%3F.html'];
        yield 'windows backslashes' => ['C:\Users\John Doe\AppData\Local\Temp\chrome_pdf_1\index.html', 'file:///C:/Users/John%20Doe/AppData/Local/Temp/chrome_pdf_1/index.html'];
        yield 'windows forward slashes and lowercase drive' => ['d:/temp/index.html', 'file:///D:/temp/index.html'];
        yield 'unc' => ['\\\\server\share\dir\index.html', 'file://server/share/dir/index.html'];
    }

    #[DataProvider('providePaths')]
    public function testFromPath(string $path, string $expectedUrl): void
    {
        self::assertSame($expectedUrl, FileUrl::fromPath($path));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideUrls(): iterable
    {
        yield 'posix' => ['file:///var/a%20b/index.html', '/var/a b/index.html'];
        yield 'localhost host' => ['file://localhost/etc/passwd', '/etc/passwd'];
        yield 'query and fragment stripped' => ['file:///var/x.html?a=1#top', '/var/x.html'];
        yield 'windows drive' => ['file:///C:/Users/x/index.html', 'C:/Users/x/index.html'];
        yield 'windows drive with pipe' => ['file:///c|/Users/x', 'C:/Users/x'];
        yield 'unc' => ['file://server/share/dir/index.html', '//server/share/dir/index.html'];
        yield 'encoded traversal is decoded' => ['file:///var/app/%2e%2e/secret', '/var/app/../secret'];
        yield 'uppercase scheme' => ['FILE:///etc/hosts', '/etc/hosts'];
    }

    #[DataProvider('provideUrls')]
    public function testToPath(string $url, string $expectedPath): void
    {
        self::assertSame($expectedPath, FileUrl::toPath($url));
    }

    public function testToPathReturnsNullForOtherSchemes(): void
    {
        self::assertNull(FileUrl::toPath('https://example.com/file:///etc/passwd'));
    }

    public function testRoundTripOnWindowsPath(): void
    {
        $path = 'C:\Program Files (x86)\Temp dir\chrome_pdf_ab12\logo 1.png';

        self::assertSame(str_replace('\\', '/', $path), FileUrl::toPath(FileUrl::fromPath($path)));
    }

    public function testRelativePathIsRejected(): void
    {
        $this->expectException(BrowserException::class);

        FileUrl::fromPath('relative/index.html');
    }
}
