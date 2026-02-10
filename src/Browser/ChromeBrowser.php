<?php

namespace Daif\ChromePdfBundle\Browser;

use HeadlessChromium\Browser;
use HeadlessChromium\BrowserFactory;
use HeadlessChromium\Cookies\Cookie;
use HeadlessChromium\Cookies\CookiesCollection;
use HeadlessChromium\Page;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;

final class ChromeBrowser implements BrowserInterface
{
    private Browser|null $browser = null;

    /**
     * @param array<string, mixed> $browserOptions Options for BrowserFactory::createBrowser()
     */
    public function __construct(
        private readonly string|null $chromeBinary = null,
        private readonly array $browserOptions = [],
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    public function htmlToPdf(string $html, array $pdfOptions = [], array $pageOptions = []): string
    {
        $tempDir = $this->createTempDir();

        try {
            $htmlPath = $tempDir.'/index.html';
            file_put_contents($htmlPath, $html);

            $this->copyAssetsToTempDir($pageOptions['assets'] ?? [], $tempDir);

            $page = $this->createPage();
            $this->configurePage($page, $pageOptions);

            $page->navigate('file://'.$htmlPath)->waitForNavigation(
                $pageOptions['waitEvent'] ?? Page::LOAD,
            );

            $this->applyWait($page, $pageOptions);

            $pagePdf = $page->pdf($this->buildCdpPdfOptions($pdfOptions, $pageOptions));

            return $pagePdf->getRawBinary();
        } finally {
            $this->cleanupTempDir($tempDir);
        }
    }

    public function urlToPdf(string $url, array $pdfOptions = [], array $pageOptions = []): string
    {
        $page = $this->createPage();
        $this->configurePage($page, $pageOptions);

        $page->navigate($url)->waitForNavigation(
            $pageOptions['waitEvent'] ?? Page::LOAD,
        );

        $this->applyWait($page, $pageOptions);

        $pagePdf = $page->pdf($this->buildCdpPdfOptions($pdfOptions, $pageOptions));

        return $pagePdf->getRawBinary();
    }

    public function htmlToScreenshot(string $html, array $screenshotOptions = [], array $pageOptions = []): string
    {
        $tempDir = $this->createTempDir();

        try {
            $htmlPath = $tempDir.'/index.html';
            file_put_contents($htmlPath, $html);

            $this->copyAssetsToTempDir($pageOptions['assets'] ?? [], $tempDir);

            $page = $this->createPage();
            $this->configurePage($page, $pageOptions);

            if (isset($screenshotOptions['width']) || isset($screenshotOptions['height'])) {
                $page->setDeviceMetricsOverride([
                    'width' => $screenshotOptions['width'] ?? 800,
                    'height' => $screenshotOptions['height'] ?? 600,
                    'deviceScaleFactor' => 1,
                    'mobile' => false,
                ]);
                unset($screenshotOptions['width'], $screenshotOptions['height']);
            }

            $page->navigate('file://'.$htmlPath)->waitForNavigation(
                $pageOptions['waitEvent'] ?? Page::LOAD,
            );

            $this->applyWait($page, $pageOptions);

            $screenshot = $page->screenshot($screenshotOptions);

            return $screenshot->getRawBinary();
        } finally {
            $this->cleanupTempDir($tempDir);
        }
    }

    public function urlToScreenshot(string $url, array $screenshotOptions = [], array $pageOptions = []): string
    {
        $page = $this->createPage();
        $this->configurePage($page, $pageOptions);

        if (isset($screenshotOptions['width']) || isset($screenshotOptions['height'])) {
            $page->setDeviceMetricsOverride([
                'width' => $screenshotOptions['width'] ?? 800,
                'height' => $screenshotOptions['height'] ?? 600,
                'deviceScaleFactor' => 1,
                'mobile' => false,
            ]);
            unset($screenshotOptions['width'], $screenshotOptions['height']);
        }

        $page->navigate($url)->waitForNavigation(
            $pageOptions['waitEvent'] ?? Page::LOAD,
        );

        $this->applyWait($page, $pageOptions);

        $screenshot = $page->screenshot($screenshotOptions);

        return $screenshot->getRawBinary();
    }

    public function close(): void
    {
        $this->browser?->close();
        $this->browser = null;
    }

    public function __destruct()
    {
        $this->close();
    }

    private function getBrowser(): Browser
    {
        if (null === $this->browser) {
            $factory = new BrowserFactory($this->chromeBinary);

            $options = array_merge([
                'headless' => true,
                'noSandbox' => true,
                'ignoreCertificateErrors' => true,
            ], $this->browserOptions);

            if (null !== $this->logger) {
                $options['debugLogger'] = $this->logger;
            }

            $this->browser = $factory->createBrowser($options);
        }

        return $this->browser;
    }

    private function createPage(): Page
    {
        return $this->getBrowser()->createPage();
    }

    /**
     * @param array<string, mixed> $pageOptions
     */
    private function configurePage(Page $page, array $pageOptions): void
    {
        if (isset($pageOptions['cookies'])) {
            $cookies = [];
            foreach ($pageOptions['cookies'] as $cookie) {
                $cookies[] = Cookie::create(
                    $cookie['name'],
                    $cookie['value'],
                    $cookie,
                );
            }
            $page->setCookies(new CookiesCollection($cookies))->await();
        }

        if (isset($pageOptions['extraHttpHeaders'])) {
            $page->setExtraHTTPHeaders($pageOptions['extraHttpHeaders']);
        }

        if (isset($pageOptions['userAgent'])) {
            $page->setUserAgent($pageOptions['userAgent'])->await();
        }

        if (isset($pageOptions['emulatedMediaType'])) {
            $page->getSession()->sendMessage(
                new \HeadlessChromium\Communication\Message(
                    'Emulation.setEmulatedMedia',
                    ['media' => $pageOptions['emulatedMediaType']],
                ),
            )->waitForResponse();
        }
    }

    /**
     * @param array<string, mixed> $pageOptions
     */
    private function applyWait(Page $page, array $pageOptions): void
    {
        if (isset($pageOptions['waitDelay'])) {
            usleep($this->parseDelay($pageOptions['waitDelay']));
        }

        if (isset($pageOptions['waitForExpression'])) {
            $page->evaluate($pageOptions['waitForExpression'])->waitForResponse();
        }
    }

    /**
     * @param array<string, mixed> $pdfOptions
     * @param array<string, mixed> $pageOptions
     *
     * @return array<string, mixed>
     */
    private function buildCdpPdfOptions(array $pdfOptions, array $pageOptions): array
    {
        $cdpOptions = [];

        $mapping = [
            'paperWidth' => 'paperWidth',
            'paperHeight' => 'paperHeight',
            'marginTop' => 'marginTop',
            'marginBottom' => 'marginBottom',
            'marginLeft' => 'marginLeft',
            'marginRight' => 'marginRight',
            'landscape' => 'landscape',
            'printBackground' => 'printBackground',
            'scale' => 'scale',
            'pageRanges' => 'pageRanges',
            'preferCSSPageSize' => 'preferCSSPageSize',
        ];

        foreach ($mapping as $from => $to) {
            if (isset($pdfOptions[$from])) {
                $cdpOptions[$to] = $pdfOptions[$from];
            }
        }

        if (isset($pdfOptions['headerTemplate']) || isset($pdfOptions['footerTemplate'])) {
            $cdpOptions['displayHeaderFooter'] = true;
            if (isset($pdfOptions['headerTemplate'])) {
                $cdpOptions['headerTemplate'] = $pdfOptions['headerTemplate'];
            }
            if (isset($pdfOptions['footerTemplate'])) {
                $cdpOptions['footerTemplate'] = $pdfOptions['footerTemplate'];
            }
        }

        return $cdpOptions;
    }

    private function createTempDir(): string
    {
        $tempDir = sys_get_temp_dir().'/chrome_pdf_'.uniqid('', true);
        (new Filesystem())->mkdir($tempDir);

        return $tempDir;
    }

    /**
     * @param array<string, \SplFileInfo> $assets
     */
    private function copyAssetsToTempDir(array $assets, string $tempDir): void
    {
        $filesystem = new Filesystem();
        foreach ($assets as $path => $fileInfo) {
            $targetPath = $tempDir.'/'.basename((string) $path);
            $filesystem->copy($fileInfo->getPathname(), $targetPath);
        }
    }

    private function cleanupTempDir(string $tempDir): void
    {
        (new Filesystem())->remove($tempDir);
    }

    private function parseDelay(string $delay): int
    {
        if (preg_match('/^(\d+(?:\.\d+)?)(ms|s)?$/', $delay, $matches)) {
            $value = (float) $matches[1];
            $unit = $matches[2] ?? 's';

            return (int) ($value * ('s' === $unit ? 1_000_000 : 1_000));
        }

        return 0;
    }
}
