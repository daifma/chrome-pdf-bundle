<?php

namespace Daif\ChromePdfBundle\Tests\Browser;

use Daif\ChromePdfBundle\Browser\ChromeBrowser;
use Daif\ChromePdfBundle\Browser\PrintOptions;
use Daif\ChromePdfBundle\Exception\BrowserException;
use Daif\ChromePdfBundle\Tests\Functional\ChromeAvailability;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Real rendering with a local Chrome / Chromium / Edge and a PHP built-in web server.
 */
#[Group('browser')]
final class ChromeBrowserTest extends TestCase
{
    private const ASSETS = __DIR__.'/../Fixtures/assets';

    private static ChromeBrowser|null $browser = null;
    private static Process|null $server = null;
    private static string $baseUrl = '';
    private static string $tempDir = '';

    public static function setUpBeforeClass(): void
    {
        self::$tempDir = sys_get_temp_dir().\DIRECTORY_SEPARATOR.'chrome_pdf_browser_test_'.bin2hex(random_bytes(4));
        (new Filesystem())->mkdir(self::$tempDir);
    }

    public static function tearDownAfterClass(): void
    {
        self::$browser?->close();
        self::$browser = null;
        self::$server?->stop(1);
        self::$server = null;
        (new Filesystem())->remove(self::$tempDir);
    }

    protected function setUp(): void
    {
        $binary = ChromeAvailability::binaryOrSkip();
        self::$browser ??= self::createBrowser($binary);
    }

    public function testBasicPdf(): void
    {
        $pdf = self::browser()->htmlToPdf('<h1>Hello Chrome PDF</h1>');

        self::assertStringStartsWith('%PDF-', $pdf);
        self::assertStringContainsString('%%EOF', substr($pdf, -1024));
        self::assertSame(1, PrintOptions::countPdfPages($pdf));
        self::assertStringContainsString('Hello Chrome PDF', self::pdfText($pdf));
    }

    public function testTempDirectoryIsCleanedUp(): void
    {
        self::browser()->htmlToPdf('<p>x</p>', [], ['assets' => ['logo.png' => new \SplFileInfo(self::ASSETS.'/logo.png')]]);

        self::assertSame([], glob(self::$tempDir.\DIRECTORY_SEPARATOR.'chrome_pdf_*') ?: []);
    }

    public function testAssetsAreLoadedFromTheConversionDirectory(): void
    {
        $html = '<img id="logo" src="logo.png"><script>document.getElementById("logo").addEventListener("load", () => { window.loaded = true; });</script>';

        $pdf = self::browser()->htmlToPdf($html, [], [
            'assets' => ['logo.png' => new \SplFileInfo(self::ASSETS.'/logo.png')],
            'waitForExpression' => 'window.loaded === true',
            'failOnResourceLoadingFailed' => true,
        ]);

        self::assertStringStartsWith('%PDF-', $pdf);
    }

    public function testDuplicateAssetBasenameIsRejected(): void
    {
        $this->expectException(BrowserException::class);
        $this->expectExceptionMessage('same file name "document.pdf"');

        self::browser()->htmlToPdf('<p>x</p>', [], ['assets' => [
            'a/document.pdf' => new \SplFileInfo(self::ASSETS.'/pdf/document.pdf'),
            'b/document.pdf' => new \SplFileInfo(self::ASSETS.'/pdf/other_document.pdf'),
        ]]);
    }

    public function testHeaderAndFooter(): void
    {
        $pdf = self::browser()->htmlToPdf('<p>BODY TEXT</p>', [
            'marginTop' => 1.0,
            'marginBottom' => 1.0,
            'headerTemplate' => '<div style="font-size: 10px">HEADER TEXT</div>',
            'footerTemplate' => '<div style="font-size: 10px">Page <span class="pageNumber"></span> of <span class="totalPages"></span></div>',
        ]);
        $text = self::pdfText($pdf);

        self::assertStringContainsString('HEADER TEXT', $text);
        self::assertStringContainsString('Page 1 of 1', $text);
        self::assertStringContainsString('BODY TEXT', $text);
    }

    public function testHeaderOnlyDoesNotPrintTheDefaultFooter(): void
    {
        $pdf = self::browser()->htmlToPdf('<html><head><title>DEFAULT TITLE</title></head><body><p>BODY</p></body></html>', [
            'marginTop' => 1.0,
            'marginBottom' => 1.0,
            'headerTemplate' => '<div style="font-size: 10px">ONLY HEADER</div>',
        ]);
        $text = self::pdfText($pdf);

        self::assertStringContainsString('ONLY HEADER', $text);
        self::assertStringNotContainsString('DEFAULT TITLE', $text);
        self::assertStringNotContainsString('file://', $text);
    }

    public function testSinglePage(): void
    {
        $html = '<div style="height: 4000px; border: 1px solid">tall</div><p>END OF DOCUMENT</p>';

        $multiple = self::browser()->htmlToPdf($html);
        self::assertGreaterThan(1, PrintOptions::countPdfPages($multiple));

        $single = self::browser()->htmlToPdf($html, ['singlePage' => true, 'pageRanges' => '2', 'marginTop' => 0.5, 'marginBottom' => 0.5]);
        self::assertSame(1, PrintOptions::countPdfPages($single));
        self::assertStringContainsString('END OF DOCUMENT', self::pdfText($single));
        [, $height] = self::pageSize($single);
        self::assertGreaterThan(4000 * 72 / 96, $height);
    }

    public function testDocumentOutlineAndTaggedPdf(): void
    {
        $html = '<h1>Chapter one</h1><p>a</p><h2>Section</h2><p>b</p>';

        self::assertFalse(str_contains(self::browser()->htmlToPdf($html), '/Outlines'));

        // recent Chrome versions tag PDFs by default, so only the positive case is asserted
        $pdf = self::browser()->htmlToPdf($html, ['generateDocumentOutline' => true, 'generateTaggedPdf' => true]);
        self::assertTrue(str_contains($pdf, '/Outlines'), 'the PDF has an outline');
        self::assertTrue(str_contains($pdf, '/StructTreeRoot'), 'the PDF is tagged');
    }

    public function testPaperSizeAndLandscape(): void
    {
        $portrait = self::browser()->htmlToPdf('<p>x</p>', ['paperWidth' => 8.27, 'paperHeight' => 11.7]);
        [$width, $height] = self::pageSize($portrait);
        self::assertEqualsWithDelta(8.27 * 72, $width, 1.5);
        self::assertEqualsWithDelta(11.7 * 72, $height, 1.5);

        $landscape = self::browser()->htmlToPdf('<p>x</p>', ['paperWidth' => 8.27, 'paperHeight' => 11.7, 'landscape' => true]);
        [$width, $height] = self::pageSize($landscape);
        self::assertEqualsWithDelta(11.7 * 72, $width, 1.5);
        self::assertEqualsWithDelta(8.27 * 72, $height, 1.5);
    }

    public function testPageRanges(): void
    {
        $pdf = self::browser()->htmlToPdf('<div style="height: 3000px">x</div>', ['pageRanges' => '2']);

        self::assertSame(1, PrintOptions::countPdfPages($pdf));
    }

    public function testWaitForExpression(): void
    {
        $html = '<p id="t">NOT READY</p><script>setTimeout(() => { document.getElementById("t").textContent = "READY NOW"; window.done = true; }, 400);</script>';

        $pdf = self::browser()->htmlToPdf($html, [], ['waitForExpression' => 'window.done === true']);

        self::assertStringContainsString('READY NOW', self::pdfText($pdf));
    }

    public function testWaitForExpressionTimesOut(): void
    {
        $browser = self::createBrowser(ChromeAvailability::binaryOrSkip(), timeout: 1500);
        $start = hrtime(true);

        try {
            $browser->htmlToPdf('<p>x</p>', [], ['waitForExpression' => 'window.never === true']);
            self::fail('A BrowserException was expected.');
        } catch (BrowserException $e) {
            self::assertStringContainsString('Timed out after 1500 ms waiting for the expression "window.never === true"', $e->getMessage());
        } finally {
            $browser->close();
        }

        self::assertLessThan(10, (hrtime(true) - $start) / 1e9);
    }

    public function testWaitForSelector(): void
    {
        $html = '<script>setTimeout(() => { const p = document.createElement("p"); p.className = "late"; p.textContent = "LATE ELEMENT"; document.body.appendChild(p); }, 400);</script>';

        $pdf = self::browser()->htmlToPdf($html, [], ['waitForSelector' => 'p.late']);

        self::assertStringContainsString('LATE ELEMENT', self::pdfText($pdf));
    }

    public function testWaitForSelectorTimesOut(): void
    {
        $browser = self::createBrowser(ChromeAvailability::binaryOrSkip(), timeout: 1000);

        try {
            $this->expectException(BrowserException::class);
            $this->expectExceptionMessage('waiting for the selector "#missing"');

            $browser->htmlToPdf('<p>x</p>', [], ['waitForSelector' => '#missing']);
        } finally {
            $browser->close();
        }
    }

    public function testWaitDelayAndNetworkIdle(): void
    {
        $html = '<p id="t">BEFORE</p><script>setTimeout(() => { document.getElementById("t").textContent = "AFTER DELAY"; }, 200);</script>';

        $pdf = self::browser()->htmlToPdf($html, [], ['waitDelay' => '700ms', 'waitEvent' => 'networkIdle']);

        self::assertStringContainsString('AFTER DELAY', self::pdfText($pdf));
    }

    public function testEmulatedMediaTypeAndFeatures(): void
    {
        $html = '<style>.screen, .dark { display: none } @media screen { .screen { display: block } } @media (prefers-color-scheme: dark) { .dark { display: block } }</style><p class="screen">SCREEN MEDIA</p><p class="dark">DARK SCHEME</p><p>ALWAYS</p>';

        $text = self::pdfText(self::browser()->htmlToPdf($html));
        self::assertStringNotContainsString('SCREEN MEDIA', $text);
        self::assertStringNotContainsString('DARK SCHEME', $text);

        $text = self::pdfText(self::browser()->htmlToPdf($html, [], [
            'emulatedMediaType' => 'screen',
            'emulatedMediaFeatures' => [['name' => 'prefers-color-scheme', 'value' => 'dark']],
        ]));
        self::assertStringContainsString('SCREEN MEDIA', $text);
        self::assertStringContainsString('DARK SCHEME', $text);
    }

    public function testFailOnConsoleExceptions(): void
    {
        $html = '<script>throw new Error("boom from the page");</script>';

        self::assertStringStartsWith('%PDF-', self::browser()->htmlToPdf($html));

        $this->expectException(BrowserException::class);
        $this->expectExceptionMessage('boom from the page');

        self::browser()->htmlToPdf($html, [], ['failOnConsoleExceptions' => true]);
    }

    public function testLocalFileInclusionIsBlocked(): void
    {
        $secretFile = self::$tempDir.\DIRECTORY_SEPARATOR.'secret.txt';
        file_put_contents($secretFile, 'TOP SECRET CONTENT');
        $secretUrl = 'file://'.('\\' === \DIRECTORY_SEPARATOR ? '/' : '').str_replace('\\', '/', $secretFile);

        $html = <<<HTML
            <p>VISIBLE</p>
            <iframe src="{$secretUrl}" width="600" height="100"></iframe>
            <object data="{$secretUrl}" width="600" height="100"></object>
            <embed src="{$secretUrl}" width="600" height="100">
            <link rel="stylesheet" href="{$secretUrl}">
            <p id="fetch"></p>
            <script>
                fetch("{$secretUrl}").then(r => r.text()).then(t => { document.getElementById("fetch").textContent = "FETCHED " + t; }, () => { document.getElementById("fetch").textContent = "FETCH BLOCKED"; }).finally(() => { window.done = true; });
            </script>
            HTML;

        $text = self::pdfText(self::browser()->htmlToPdf($html, [], ['waitForExpression' => 'window.done === true']));

        self::assertStringContainsString('VISIBLE', $text);
        self::assertStringContainsString('FETCH BLOCKED', $text);
        self::assertStringNotContainsString('TOP SECRET', $text);
    }

    public function testTopLevelNavigationToALocalFileIsBlocked(): void
    {
        $secretFile = self::$tempDir.\DIRECTORY_SEPARATOR.'secret-nav.txt';
        file_put_contents($secretFile, 'NAVIGATED SECRET');
        $secretUrl = 'file://'.('\\' === \DIRECTORY_SEPARATOR ? '/' : '').str_replace('\\', '/', $secretFile);

        $this->expectException(BrowserException::class);
        $this->expectExceptionMessage('local files outside the conversion directory are not accessible');

        self::browser()->htmlToPdf('<p>ORIGINAL</p><script>location.href = '.json_encode($secretUrl).';</script>', [], ['waitDelay' => '300ms']);
    }

    public function testBlockedResourceIsReportedByFailOnResourceLoadingFailed(): void
    {
        $this->expectException(BrowserException::class);
        $this->expectExceptionMessageMatches('#file:///.*passwd.*local files outside the conversion directory are not accessible#');

        self::browser()->htmlToPdf('<img src="file:///etc/passwd">', [], ['failOnResourceLoadingFailed' => true]);
    }

    public function testFileUrlTargetIsRejected(): void
    {
        $this->expectException(BrowserException::class);
        $this->expectExceptionMessage('Only http and https URLs can be converted');

        self::browser()->urlToPdf('file:///etc/passwd');
    }

    public function testUrlToPdf(): void
    {
        $pdf = self::browser()->urlToPdf(self::serverUrl('/status/200'));

        self::assertStringContainsString('STATUS 200', self::pdfText($pdf));
    }

    public function testFailOnHttpStatusCodes(): void
    {
        self::assertStringStartsWith('%PDF-', self::browser()->urlToPdf(self::serverUrl('/status/404')));
        self::assertStringStartsWith('%PDF-', self::browser()->urlToPdf(self::serverUrl('/status/200'), [], ['failOnHttpStatusCodes' => [499]]));

        $this->expectException(BrowserException::class);
        $this->expectExceptionMessageMatches('#Invalid HTTP status code 404 for the main page "http://127\.0\.0\.1:\d+/status/404"#');

        self::browser()->urlToPdf(self::serverUrl('/status/404'), [], ['failOnHttpStatusCodes' => [499]]);
    }

    public function testFailOnResourceHttpStatusCodes(): void
    {
        $url = self::serverUrl('/image-status?code=503');

        self::assertStringStartsWith('%PDF-', self::browser()->urlToPdf($url, [], ['failOnResourceHttpStatusCodes' => [404]]));

        $this->expectException(BrowserException::class);
        $this->expectExceptionMessageMatches('#Invalid HTTP status code for 1 resource\(s\): 503 http://127\.0\.0\.1:\d+/status/503\?img#');

        self::browser()->urlToPdf($url, [], ['failOnResourceHttpStatusCodes' => [599]]);
    }

    public function testIgnoreResourceHttpStatusDomains(): void
    {
        $pdf = self::browser()->urlToPdf(self::serverUrl('/image-status?code=404&host=localhost'), [], [
            'failOnResourceHttpStatusCodes' => [499],
            'ignoreResourceHttpStatusDomains' => ['localhost'],
        ]);

        self::assertStringStartsWith('%PDF-', $pdf);
    }

    public function testFailOnResourceLoadingFailed(): void
    {
        $url = 'http://127.0.0.1:'.self::closedPort().'/unreachable.png';
        $html = '<img src="'.$url.'">';

        self::assertStringStartsWith('%PDF-', self::browser()->htmlToPdf($html));

        $this->expectException(BrowserException::class);
        $this->expectExceptionMessage($url.': net::ERR_CONNECTION_REFUSED');

        self::browser()->htmlToPdf($html, [], ['failOnResourceLoadingFailed' => true]);
    }

    public function testCookiesHeadersAndUserAgentReachTheServer(): void
    {
        $pdf = self::browser()->urlToPdf(self::serverUrl('/echo'), [], [
            'cookies' => [['name' => 'chrome_pdf_test', 'value' => 'cookie-value', 'domain' => '127.0.0.1', 'sameSite' => 'lax']],
            'extraHttpHeaders' => ['X-Chrome-Pdf-Test' => 'header-value'],
            'userAgent' => 'ChromePdfTestAgent/1.0',
        ]);
        $text = self::pdfText($pdf);

        self::assertStringContainsString('COOKIE=cookie-value', $text);
        self::assertStringContainsString('HEADER=header-value', $text);
        self::assertStringContainsString('AGENT=ChromePdfTestAgent/1.0', $text);
    }

    public function testAllowAndDenyLists(): void
    {
        $binary = ChromeAvailability::binaryOrSkip();
        $browser = self::createBrowser($binary, denyList: ['#/status/5\d\d#']);

        try {
            self::assertStringStartsWith('%PDF-', $browser->urlToPdf(self::serverUrl('/status/200')));

            try {
                $browser->urlToPdf(self::serverUrl('/status/500'));
                self::fail('A BrowserException was expected.');
            } catch (BrowserException $e) {
                self::assertStringContainsString('the URL matches the deny list', $e->getMessage());
            }

            try {
                $browser->urlToPdf(self::serverUrl('/image-status?code=503'), [], ['failOnResourceLoadingFailed' => true]);
                self::fail('A BrowserException was expected.');
            } catch (BrowserException $e) {
                self::assertStringContainsString('/status/503?img', $e->getMessage());
                self::assertStringContainsString('the URL matches the deny list', $e->getMessage());
            }
        } finally {
            $browser->close();
        }

        $browser = self::createBrowser($binary, allowList: ['#^https://allowed\.example/#']);
        try {
            $this->expectException(BrowserException::class);
            $this->expectExceptionMessage('the URL does not match the allow list');

            $browser->urlToPdf(self::serverUrl('/status/200'));
        } finally {
            $browser->close();
        }
    }

    public function testFullPageScreenshotVersusClip(): void
    {
        $html = '<style>body { margin: 0 }</style><div style="height: 2000px; background: red"></div>';

        $full = self::browser()->htmlToScreenshot($html, ['width' => 800, 'height' => 600]);
        self::assertSame([800, 2000], self::pngSize($full));

        $clip = self::browser()->htmlToScreenshot($html, ['width' => 800, 'height' => 600, 'clip' => true]);
        self::assertSame([800, 600], self::pngSize($clip));

        $short = self::browser()->htmlToScreenshot('<p>short</p>', ['width' => 640, 'height' => 480]);
        self::assertSame([640, 480], self::pngSize($short), 'a full page screenshot is at least as tall as the viewport');
    }

    public function testDeviceScaleFactor(): void
    {
        $png = self::browser()->htmlToScreenshot('<p>retina</p>', ['width' => 400, 'height' => 300, 'clip' => true, 'deviceScaleFactor' => 2.0]);

        self::assertSame([800, 600], self::pngSize($png));
    }

    public function testOmitBackgroundProducesTransparentPixels(): void
    {
        $opaque = self::browser()->htmlToScreenshot('<p>x</p>', ['width' => 100, 'height' => 100, 'clip' => true]);
        self::assertSame(255, self::firstPixel($opaque)[3]);

        $transparent = self::browser()->htmlToScreenshot('<p>x</p>', ['width' => 100, 'height' => 100, 'clip' => true, 'quality' => 50], ['omitBackground' => true]);
        self::assertSame(0, self::firstPixel($transparent)[3]);
    }

    public function testOmitBackgroundInPdf(): void
    {
        $pdf = self::browser()->htmlToPdf('<p>x</p>', ['printBackground' => true], ['omitBackground' => true]);

        self::assertStringStartsWith('%PDF-', $pdf);
    }

    public function testJpegAndWebpScreenshots(): void
    {
        $jpeg = self::browser()->htmlToScreenshot('<p>jpeg</p>', ['width' => 320, 'height' => 200, 'clip' => true, 'format' => 'jpeg', 'quality' => 40]);
        self::assertStringStartsWith("\xFF\xD8\xFF", $jpeg);
        $size = getimagesizefromstring($jpeg);
        self::assertNotFalse($size);
        self::assertSame([320, 200], [$size[0], $size[1]]);

        $webp = self::browser()->htmlToScreenshot('<p>webp</p>', ['format' => 'webp', 'optimizeForSpeed' => true]);
        self::assertStringStartsWith('RIFF', $webp);
        self::assertSame('WEBP', substr($webp, 8, 4));
    }

    public function testUrlToScreenshot(): void
    {
        $png = self::browser()->urlToScreenshot(self::serverUrl('/status/200'), ['width' => 500, 'height' => 400, 'clip' => true]);

        self::assertSame([500, 400], self::pngSize($png));
    }

    public function testRecoversFromADeadChromeProcess(): void
    {
        $browser = self::createBrowser(ChromeAvailability::binaryOrSkip());

        try {
            self::assertStringStartsWith('%PDF-', $browser->htmlToPdf('<p>first</p>'));

            $property = new \ReflectionProperty(ChromeBrowser::class, 'browser');
            $process = $property->getValue($browser);
            self::assertInstanceOf(\HeadlessChromium\Browser::class, $process);
            // kills the process but keeps the stale instance in the service
            $process->close();

            self::assertStringStartsWith('%PDF-', $browser->htmlToPdf('<p>second</p>'));
            self::assertNotSame($process, $property->getValue($browser));

            $browser->close();
            self::assertStringStartsWith('%PDF-', $browser->htmlToPdf('<p>after close</p>'));
        } finally {
            $browser->close();
        }
    }

    public function testTabsAreClosed(): void
    {
        self::browser()->htmlToPdf('<p>1</p>');
        self::browser()->htmlToScreenshot('<p>2</p>');
        try {
            self::browser()->htmlToPdf('<p>3</p>', [], ['failOnConsoleExceptions' => true, 'waitForExpression' => 'missing.variable']);
        } catch (BrowserException) {
        }

        $property = new \ReflectionProperty(ChromeBrowser::class, 'browser');
        $process = $property->getValue(self::browser());
        self::assertInstanceOf(\HeadlessChromium\Browser::class, $process);

        $pages = array_filter($process->getTargets(), static fn ($target): bool => 'page' === $target->getTargetInfo('type'));
        self::assertLessThanOrEqual(1, \count($pages), 'only the initial blank tab may remain');
    }

    private static function browser(): ChromeBrowser
    {
        self::assertNotNull(self::$browser);

        return self::$browser;
    }

    /**
     * @param list<string> $allowList
     * @param list<string> $denyList
     */
    private static function createBrowser(string $binary, int $timeout = 15000, array $allowList = [], array $denyList = []): ChromeBrowser
    {
        return new ChromeBrowser(
            chromeBinary: $binary,
            timeout: $timeout,
            noSandbox: ChromeAvailability::noSandbox(),
            tempDir: self::$tempDir,
            allowList: $allowList,
            denyList: $denyList,
        );
    }

    private static function closedPort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        self::assertNotFalse($socket);
        $address = (string) stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr($address, strrpos($address, ':') + 1);
    }

    public function testClientSideRedirectIsFollowed(): void
    {
        $target = self::serverUrl('/status/200');

        $pdf = self::browser()->htmlToPdf('<p>REDIRECTING</p><script>location.href = '.json_encode($target).';</script>', [], ['failOnHttpStatusCodes' => [499]]);

        self::assertStringContainsString('STATUS 200', self::pdfText($pdf));
    }

    private static function serverUrl(string $path): string
    {
        if (null === self::$server) {
            $php = (new PhpExecutableFinder())->find(false);
            if (false === $php) {
                self::markTestSkipped('PHP binary not found for the built-in web server.');
            }

            $socket = stream_socket_server('tcp://127.0.0.1:0');
            self::assertNotFalse($socket);
            $address = (string) stream_socket_get_name($socket, false);
            fclose($socket);

            $server = new Process([$php, '-S', $address, __DIR__.'/../Fixtures/browser/router.php'], null, ['PHP_CLI_SERVER_WORKERS' => '4']);
            $server->start();

            $deadline = microtime(true) + 10;
            while (false === ($connection = @fsockopen('127.0.0.1', (int) substr($address, strrpos($address, ':') + 1), $errno, $errstr, 0.2))) {
                if (microtime(true) > $deadline || !$server->isRunning()) {
                    self::fail('Unable to start the PHP built-in web server: '.$server->getErrorOutput());
                }
                usleep(50_000);
            }
            fclose($connection);

            self::$server = $server;
            self::$baseUrl = 'http://'.$address;
        }

        return self::$baseUrl.$path;
    }

    private static function pdfText(string $pdf): string
    {
        $pdftotext = (new ExecutableFinder())->find('pdftotext');
        if (null === $pdftotext) {
            self::markTestSkipped('pdftotext (poppler-utils) is required to read the PDF text.');
        }

        // xpdf's pdftotext (shipped with Git for Windows) cannot read the PDF from stdin
        $file = tempnam(sys_get_temp_dir(), 'chrome_pdf_test');
        self::assertIsString($file);
        try {
            file_put_contents($file, $pdf);
            $process = new Process([$pdftotext, '-layout', $file, '-']);
            $process->mustRun();

            return $process->getOutput();
        } finally {
            @unlink($file);
        }
    }

    /**
     * @return array{float, float} first page MediaBox width and height, in points
     */
    private static function pageSize(string $pdf): array
    {
        self::assertSame(1, preg_match('#/MediaBox\s*\[\s*([\d.]+)\s+([\d.]+)\s+([\d.]+)\s+([\d.]+)\s*\]#', $pdf, $matches), 'MediaBox not found');

        return [(float) $matches[3] - (float) $matches[1], (float) $matches[4] - (float) $matches[2]];
    }

    /**
     * @return array{int, int}
     */
    private static function pngSize(string $png): array
    {
        self::assertStringStartsWith("\x89PNG\r\n\x1a\n", $png);
        $header = unpack('Nwidth/Nheight', substr($png, 16, 8));
        self::assertIsArray($header);

        return [$header['width'], $header['height']];
    }

    /**
     * @return array{int, int, int, int} RGBA of the top-left pixel of an 8-bit RGBA PNG
     */
    private static function firstPixel(string $png): array
    {
        self::assertStringStartsWith("\x89PNG\r\n\x1a\n", $png);
        self::assertSame(8, \ord($png[24]), 'bit depth');

        $offset = 8;
        $colorType = null;
        $idat = '';
        while ($offset < \strlen($png)) {
            $chunk = unpack('Nlength', substr($png, $offset, 4));
            self::assertIsArray($chunk);
            $type = substr($png, $offset + 4, 4);
            $data = substr($png, $offset + 8, $chunk['length']);
            if ('IHDR' === $type) {
                $colorType = \ord($data[9]);
            } elseif ('IDAT' === $type) {
                $idat .= $data;
            }
            $offset += 12 + $chunk['length'];
        }

        $raw = zlib_decode($idat);
        self::assertIsString($raw);

        // the first pixel of the first row is never predicted from other bytes, whatever the filter
        return match ($colorType) {
            6 => [\ord($raw[1]), \ord($raw[2]), \ord($raw[3]), \ord($raw[4])],
            2 => [\ord($raw[1]), \ord($raw[2]), \ord($raw[3]), 255],
            default => self::fail(\sprintf('Unsupported PNG color type %s.', var_export($colorType, true))),
        };
    }
}
