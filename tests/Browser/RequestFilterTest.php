<?php

namespace Daif\ChromePdfBundle\Tests\Browser;

use Daif\ChromePdfBundle\Browser\RequestFilter;
use Daif\ChromePdfBundle\Exception\BrowserException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RequestFilterTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function provideFileUrls(): iterable
    {
        yield 'index inside directory' => ['/var/conv/chrome_pdf_1', 'file:///var/conv/chrome_pdf_1/index.html', true];
        yield 'asset inside directory' => ['/var/conv/chrome_pdf_1', 'file:///var/conv/chrome_pdf_1/logo.png', true];
        yield 'system file' => ['/var/conv/chrome_pdf_1', 'file:///etc/passwd', false];
        yield 'sibling directory with common prefix' => ['/var/conv/chrome_pdf_1', 'file:///var/conv/chrome_pdf_10/index.html', false];
        yield 'traversal' => ['/var/conv/chrome_pdf_1', 'file:///var/conv/chrome_pdf_1/../../../etc/passwd', false];
        yield 'encoded traversal' => ['/var/conv/chrome_pdf_1', 'file:///var/conv/chrome_pdf_1/%2e%2e/%2e%2e/etc/passwd', false];
        yield 'localhost host' => ['/var/conv/chrome_pdf_1', 'file://localhost/var/conv/chrome_pdf_1/index.html', true];
        yield 'remote host' => ['/var/conv/chrome_pdf_1', 'file://evil/var/conv/chrome_pdf_1/index.html', false];
        yield 'windows inside directory' => ['C:\Users\x\AppData\Local\Temp\chrome_pdf_1', 'file:///C:/Users/x/AppData/Local/Temp/chrome_pdf_1/index.html', true];
        yield 'windows case insensitive' => ['C:\Users\x\Temp\chrome_pdf_1', 'file:///c:/users/X/temp/CHROME_PDF_1/logo.png', true];
        yield 'windows other file' => ['C:\Users\x\Temp\chrome_pdf_1', 'file:///C:/Windows/win.ini', false];
        yield 'unc inside directory' => ['\\\\srv\share\chrome_pdf_1', 'file://srv/share/chrome_pdf_1/index.html', true];
        yield 'unc other share' => ['\\\\srv\share\chrome_pdf_1', 'file://srv/other/secret.txt', false];
    }

    #[DataProvider('provideFileUrls')]
    public function testFileUrls(string $directory, string $url, bool $allowed): void
    {
        self::assertSame($allowed, (new RequestFilter($directory))->isAllowed($url));
    }

    public function testFileUrlsAreBlockedWithoutDirectory(): void
    {
        $filter = new RequestFilter();

        self::assertFalse($filter->isAllowed('file:///var/conv/index.html'));
        self::assertSame('local files outside the conversion directory are not accessible', $filter->getBlockReason('file:///etc/passwd'));
    }

    public function testHttpIsAllowedByDefault(): void
    {
        $filter = new RequestFilter('/var/conv');

        self::assertTrue($filter->isAllowed('https://example.com/style.css'));
        self::assertTrue($filter->isAllowed('data:image/png;base64,AAAA'));
    }

    public function testAllowList(): void
    {
        $filter = new RequestFilter('/var/conv', ['#^https://intranet\.example\.com/#']);

        self::assertTrue($filter->isAllowed('https://intranet.example.com/a.css'));
        self::assertSame('the URL does not match the allow list', $filter->getBlockReason('https://example.org/a.css'));
        self::assertTrue($filter->isAllowed('data:text/plain,hello'), 'data: URLs are not subject to the allow list');
        self::assertTrue($filter->isAllowed('file:///var/conv/index.html'), 'the conversion directory is always allowed');
    }

    public function testDenyListWins(): void
    {
        $filter = new RequestFilter('/var/conv', ['#^https://#'], ['#^https://[^/]*\.internal/#', '#^data:#']);

        self::assertTrue($filter->isAllowed('https://example.com/'));
        self::assertSame('the URL matches the deny list', $filter->getBlockReason('https://db.internal/admin'));
        self::assertFalse($filter->isAllowed('data:text/html,<script>alert(1)</script>'));
    }

    public function testInvalidPatternIsRejected(): void
    {
        $this->expectException(BrowserException::class);
        $this->expectExceptionMessage('Invalid allow/deny list regular expression "^https://"');

        new RequestFilter(null, ['^https://']);
    }
}
