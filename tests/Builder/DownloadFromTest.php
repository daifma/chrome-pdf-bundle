<?php

namespace Daif\ChromePdfBundle\Tests\Builder;

use Daif\ChromePdfBundle\Builder\Pdf\HtmlPdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\MarkdownPdfBuilder;
use Daif\ChromePdfBundle\Builder\Screenshot\UrlScreenshotBuilder;
use Daif\ChromePdfBundle\Builder\Util\DownloadGuard;
use Daif\ChromePdfBundle\Exception\DownloadFromException;
use Daif\ChromePdfBundle\Exception\InvalidBuilderConfiguration;
use Daif\ChromePdfBundle\Exception\LogicException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class DownloadFromTest extends AbstractBuilderTestCase
{
    /** @var list<array{method: string, url: string, headers: list<string>}> */
    private array $requests = [];

    /**
     * @param array<string, MockResponse> $responses by URL
     */
    private function mockHttpClient(array $responses): void
    {
        $this->requests = [];
        $this->extraServices['http_client'] = new MockHttpClient(function (string $method, string $url, array $options) use ($responses): MockResponse {
            $this->requests[] = ['method' => $method, 'url' => $url, 'headers' => $options['headers'] ?? []];

            return $responses[$url] ?? new MockResponse('not found', ['http_code' => 404]);
        });
        $this->extraServices['.daif_chrome_pdf.download_guard'] ??= new DownloadGuard();
    }

    public function testFilesAreDownloadedAddedAsAssetsAndDeletedAfterwards(): void
    {
        $this->mockHttpClient([
            'https://cdn.example.com/img/banner.png?v=3' => new MockResponse('PNG-DATA'),
            'https://cdn.example.com/download?id=12' => new MockResponse('CSS-DATA', ['response_headers' => ['Content-Disposition' => 'attachment; filename="style.css"']]),
            'https://cdn.example.com/font' => new MockResponse('FONT-DATA', ['response_headers' => ['Content-Disposition' => "attachment; filename*=UTF-8''my%20font.woff2"]]),
        ]);

        $builder = $this->createBuilder(HtmlPdfBuilder::class)
            ->contentRaw('x')
            ->addAsset('logo.png')
            ->downloadFrom([
                ['url' => 'https://cdn.example.com/img/banner.png?v=3', 'extraHttpHeaders' => ['Authorization' => 'Bearer token']],
                ['url' => 'https://cdn.example.com/download?id=12'],
            ])
            ->downloadFrom([['url' => 'https://cdn.example.com/font']])
        ;

        $temporaryFiles = [];
        $this->browser->onCall = function () use (&$temporaryFiles): void {
            foreach ($this->browser->pageOptions['assets'] as $key => $asset) {
                if (str_contains($key, 'chrome_pdf_download_')) {
                    $temporaryFiles[] = $asset->getPathname();
                }
            }
        };

        $builder->generate();

        self::assertCount(3, $this->requests);
        self::assertContains('Authorization: Bearer token', $this->requests[0]['headers']);

        // The user asset and the downloaded files (named after Content-Disposition, or the URL basename).
        self::assertSame((string) file_get_contents(self::FIXTURE_DIR.'/assets/logo.png'), $this->browser->assetContents['logo.png']);
        self::assertSame('PNG-DATA', $this->browser->assetContents['banner.png']);
        self::assertCount(4, $this->browser->pageOptions['assets']);
        self::assertSame('CSS-DATA', $this->browser->assetContents['style.css']);
        self::assertSame('FONT-DATA', $this->browser->assetContents['my font.woff2']);

        self::assertCount(3, $temporaryFiles);
        foreach ($temporaryFiles as $file) {
            self::assertFileDoesNotExist($file);
        }
        self::assertDirectoryDoesNotExist(\dirname($temporaryFiles[0]));

        // The builder state is restored: generating again downloads again into a fresh directory.
        self::assertSame(['logo.png'], array_keys($builder->getBodyBag()->get('assets')));
    }

    public function testUrlBasenameIsUsedWithoutContentDisposition(): void
    {
        $this->mockHttpClient(['https://cdn.example.com/a/b/picture.jpeg?size=large#top' => new MockResponse('JPEG')]);

        $this->createBuilder(UrlScreenshotBuilder::class)
            ->url('https://example.com')
            ->downloadFrom([['url' => 'https://cdn.example.com/a/b/picture.jpeg?size=large#top']])
            ->generate()
        ;

        self::assertSame(['picture.jpeg' => 'JPEG'], $this->browser->assetContents);
    }

    public function testRemoteFilenamesAreSanitized(): void
    {
        $this->mockHttpClient(['https://cdn.example.com/x' => new MockResponse('DATA', ['response_headers' => ['Content-Disposition' => 'attachment; filename="..\\..\\evil.png"']])]);

        $this->createBuilder(HtmlPdfBuilder::class)->contentRaw('x')->downloadFrom([['url' => 'https://cdn.example.com/x']])->generate();

        self::assertSame(['evil.png' => 'DATA'], $this->browser->assetContents);
    }

    public function testDownloadedMarkdownFilesFeedTheMarkdownBuilder(): void
    {
        $this->mockHttpClient(['https://docs.example.com/remote.md' => new MockResponse("# Remote title\n")]);

        $this->createBuilder(MarkdownPdfBuilder::class)
            ->wrapperRaw('<main>{{ toHTML "remote.md" }}</main>')
            ->downloadFrom([['url' => 'https://docs.example.com/remote.md']])
            ->generate()
        ;

        self::assertSame("<main><h1>Remote title</h1>\n</main>", $this->browser->source);
        self::assertArrayNotHasKey('assets', $this->browser->pageOptions);
    }

    public function testHttpErrorFailsTheGenerationAndCleansUp(): void
    {
        $this->mockHttpClient([]);

        $builder = $this->createBuilder(HtmlPdfBuilder::class)->contentRaw('x')->downloadFrom([['url' => 'https://cdn.example.com/missing.png']]);

        try {
            $builder->generate();
            self::fail('A DownloadFromException was expected.');
        } catch (DownloadFromException $e) {
            self::assertStringContainsString('Could not download "https://cdn.example.com/missing.png": the server responded with HTTP status 404.', $e->getMessage());
        }

        self::assertNull($this->browser->method);
        self::assertNull($builder->getBodyBag()->get('assets'));
    }

    public function testTransportErrorIsWrapped(): void
    {
        $this->mockHttpClient(['https://cdn.example.com/a.png' => new MockResponse('', ['error' => 'Connection refused'])]);

        $this->expectException(DownloadFromException::class);
        $this->expectExceptionMessage('Connection refused');

        $this->createBuilder(HtmlPdfBuilder::class)->contentRaw('x')->downloadFrom([['url' => 'https://cdn.example.com/a.png']])->generate();
    }

    public function testDuplicateFilenamesAreRejected(): void
    {
        $this->mockHttpClient([
            'https://a.example.com/logo.png' => new MockResponse('A'),
            'https://b.example.com/logo.png' => new MockResponse('B'),
        ]);

        $this->expectException(DownloadFromException::class);
        $this->expectExceptionMessage('both resolve to the filename "logo.png"');

        $this->createBuilder(HtmlPdfBuilder::class)
            ->contentRaw('x')
            ->downloadFrom([['url' => 'https://a.example.com/logo.png'], ['url' => 'https://b.example.com/logo.png']])
            ->generate()
        ;
    }

    public function testHttpClientIsRequired(): void
    {
        $this->extraServices['.daif_chrome_pdf.download_guard'] = new DownloadGuard();
        $builder = $this->createBuilder(HtmlPdfBuilder::class)->contentRaw('x')->downloadFrom([['url' => 'https://cdn.example.com/a.png']]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('An HTTP client is required to use "downloadFrom()"');

        $builder->generate();
    }

    public function testNoHttpClientIsNeededWithoutDownloads(): void
    {
        $this->createBuilder(HtmlPdfBuilder::class)->contentRaw('x')->downloadFrom([])->generate();

        self::assertSame('htmlToPdf', $this->browser->method);
    }

    public function testOnlyHttpUrlsAreAccepted(): void
    {
        $this->expectException(InvalidBuilderConfiguration::class);

        $this->createBuilder(HtmlPdfBuilder::class)->downloadFrom([['url' => 'file:///etc/passwd']]);
    }

    public function testUnknownKeysAreRejected(): void
    {
        $this->expectException(InvalidBuilderConfiguration::class);

        $this->createBuilder(HtmlPdfBuilder::class)->downloadFrom([['url' => 'https://example.com/a', 'foo' => 'bar']]);
    }

    public function testDenyListIsAppliedBeforeAnyRequest(): void
    {
        $this->mockHttpClient(['http://169.254.169.254/latest/meta-data' => new MockResponse('secret')]);
        $this->extraServices['.daif_chrome_pdf.download_guard'] = new DownloadGuard([], ['#^https?://169\.254\.#']);

        $builder = $this->createBuilder(HtmlPdfBuilder::class)->contentRaw('x')->downloadFrom([['url' => 'http://169.254.169.254/latest/meta-data']]);

        try {
            $builder->generate();
            self::fail('The download should have been refused.');
        } catch (DownloadFromException $e) {
            self::assertStringContainsString('deny list', $e->getMessage());
        }
        self::assertSame([], $this->requests);
    }

    public function testAllowListIsApplied(): void
    {
        $this->mockHttpClient([]);
        $this->extraServices['.daif_chrome_pdf.download_guard'] = new DownloadGuard(['#^https://cdn\.example\.com/#']);

        $this->expectException(DownloadFromException::class);
        $this->expectExceptionMessage('allow list');

        $this->createBuilder(HtmlPdfBuilder::class)->contentRaw('x')->downloadFrom([['url' => 'https://intranet.local/a.png']])->generate();
    }

    public function testRedirectsAreNotFollowed(): void
    {
        $this->mockHttpClient([
            'https://cdn.example.com/a.png' => new MockResponse('', ['http_code' => 302, 'response_headers' => ['Location' => 'http://127.0.0.1/admin']]),
        ]);

        $this->expectException(DownloadFromException::class);
        $this->expectExceptionMessage('HTTP status 302');

        $this->createBuilder(HtmlPdfBuilder::class)->contentRaw('x')->downloadFrom([['url' => 'https://cdn.example.com/a.png']])->generate();
    }

    public function testConfiguredTempDirIsUsed(): void
    {
        $tempDir = sys_get_temp_dir().\DIRECTORY_SEPARATOR.'chrome_pdf_guard_'.bin2hex(random_bytes(4));
        mkdir($tempDir);
        $this->mockHttpClient(['https://cdn.example.com/a.png' => new MockResponse('PNG')]);
        $this->extraServices['.daif_chrome_pdf.download_guard'] = new DownloadGuard([], [], $tempDir);

        $paths = [];
        $this->browser->onCall = function () use (&$paths): void {
            foreach ($this->browser->pageOptions['assets'] as $asset) {
                $paths[] = $asset->getPathname();
            }
        };

        try {
            $this->createBuilder(HtmlPdfBuilder::class)->contentRaw('x')->downloadFrom([['url' => 'https://cdn.example.com/a.png']])->generate();
            self::assertCount(1, $paths);
            self::assertStringStartsWith($tempDir.\DIRECTORY_SEPARATOR, $paths[0]);
        } finally {
            @rmdir($tempDir);
        }
    }
}
