<?php

namespace Daif\ChromePdfBundle\Browser;

use Daif\ChromePdfBundle\Exception\BrowserException;
use HeadlessChromium\Browser;
use HeadlessChromium\BrowserFactory;
use HeadlessChromium\Page;
use Psr\Log\LoggerInterface;

/**
 * Converts HTML or URLs to PDF / images with a local Chrome, Chromium or Microsoft Edge.
 *
 * The Chrome process is started lazily, reused across conversions and transparently
 * restarted once when it died. Each conversion uses its own tab, always closed afterwards.
 */
final class ChromeBrowser implements BrowserInterface
{
    private Browser|null $browser = null;

    /**
     * @param array<string, mixed> $browserOptions raw chrome-php BrowserFactory options, merged last
     * @param int                  $timeout        milliseconds, applies to navigation, waits, printing and screenshots
     * @param list<string>         $allowList      PCRE patterns with delimiters, e.g. "#^https://intranet\.#"
     * @param list<string>         $denyList       PCRE patterns with delimiters
     */
    public function __construct(
        private readonly string|null $chromeBinary = null,
        private readonly array $browserOptions = [],
        private readonly LoggerInterface|null $logger = null,
        private readonly int $timeout = 30000,
        private readonly bool $noSandbox = false,
        private readonly bool $ignoreCertificateErrors = false,
        private readonly string|null $tempDir = null,
        private readonly array $allowList = [],
        private readonly array $denyList = [],
    ) {
        if ($timeout <= 0) {
            throw new BrowserException(\sprintf('The timeout must be a positive number of milliseconds, %d given.', $timeout));
        }

        // fail fast on invalid patterns
        new RequestFilter(null, $allowList, $denyList);
    }

    public function htmlToPdf(string $html, array $pdfOptions = [], array $pageOptions = []): string
    {
        return $this->convertHtml($html, $pageOptions, null, static fn (PageSession $session): string => $session->printToPdf($pdfOptions, $pageOptions));
    }

    public function urlToPdf(string $url, array $pdfOptions = [], array $pageOptions = []): string
    {
        return $this->convertUrl($url, $pageOptions, null, static fn (PageSession $session): string => $session->printToPdf($pdfOptions, $pageOptions));
    }

    public function htmlToScreenshot(string $html, array $screenshotOptions = [], array $pageOptions = []): string
    {
        $screenshotOptions = self::normalizeScreenshotOptions($screenshotOptions);

        return $this->convertHtml($html, $pageOptions, self::viewport($screenshotOptions), static fn (PageSession $session): string => $session->captureScreenshot($screenshotOptions));
    }

    public function urlToScreenshot(string $url, array $screenshotOptions = [], array $pageOptions = []): string
    {
        $screenshotOptions = self::normalizeScreenshotOptions($screenshotOptions);

        return $this->convertUrl($url, $pageOptions, self::viewport($screenshotOptions), static fn (PageSession $session): string => $session->captureScreenshot($screenshotOptions));
    }

    /**
     * Stops the Chrome process. A later conversion starts a new one.
     */
    public function close(): void
    {
        $browser = $this->browser;
        $this->browser = null;
        if (null === $browser) {
            return;
        }

        try {
            $browser->close();
        } catch (\Throwable $e) {
            $this->logger?->warning('Unable to close Chrome: {message}', ['message' => $e->getMessage(), 'exception' => $e]);
        }
    }

    public function __destruct()
    {
        $this->close();
    }

    /**
     * @param array<string, mixed>                                          $pageOptions
     * @param array{width: int, height: int, deviceScaleFactor: float}|null $viewport
     * @param callable(PageSession): string                                 $produce
     */
    private function convertHtml(string $html, array $pageOptions, array|null $viewport, callable $produce): string
    {
        $directory = WorkingDirectory::create($this->tempDir);

        try {
            $assets = $pageOptions['assets'] ?? [];
            if (!\is_array($assets)) {
                throw new BrowserException('The "assets" page option must be an array of SplFileInfo.');
            }
            $directory->copyAssets($assets);
            $index = $directory->writeFile('index.html', $html);

            $filter = new RequestFilter($directory->getPath(), $this->allowList, $this->denyList);

            return $this->render(FileUrl::fromPath($index), $filter, $pageOptions, $viewport, $produce);
        } finally {
            $directory->remove($this->logger);
        }
    }

    /**
     * @param array<string, mixed>                                          $pageOptions
     * @param array{width: int, height: int, deviceScaleFactor: float}|null $viewport
     * @param callable(PageSession): string                                 $produce
     */
    private function convertUrl(string $url, array $pageOptions, array|null $viewport, callable $produce): string
    {
        $scheme = strtolower((string) parse_url($url, \PHP_URL_SCHEME));
        if (!\in_array($scheme, ['http', 'https'], true)) {
            throw new BrowserException(\sprintf('Only http and https URLs can be converted, "%s" given.', self::shortUrl($url)));
        }

        $filter = new RequestFilter(null, $this->allowList, $this->denyList);
        $reason = $filter->getBlockReason($url);
        if (null !== $reason) {
            throw new BrowserException(\sprintf('The URL "%s" is not allowed: %s.', self::shortUrl($url), $reason));
        }

        return $this->render($url, $filter, $pageOptions, $viewport, $produce);
    }

    /**
     * @param array<string, mixed>                                          $pageOptions
     * @param array{width: int, height: int, deviceScaleFactor: float}|null $viewport
     * @param callable(PageSession): string                                 $produce
     */
    private function render(string $url, RequestFilter $filter, array $pageOptions, array|null $viewport, callable $produce): string
    {
        $page = $this->createPage();
        try {
            $session = new PageSession($page, $filter, $this->timeout, $this->logger);
        } catch (\Throwable $e) {
            // the session never took ownership of the tab
            try {
                $page->close();
            } catch (\Throwable) {
            }

            throw $e;
        }

        try {
            $session->open($url, $pageOptions, $viewport);

            return $produce($session);
        } finally {
            $session->close();
            if (!$this->isBrowserAlive()) {
                // next conversion starts a fresh process
                $this->close();
            }
        }
    }

    private function createPage(): Page
    {
        try {
            return $this->getBrowser()->createPage();
        } catch (BrowserException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->logger?->warning('Unable to open a Chrome tab, restarting Chrome: {message}', ['message' => $e->getMessage(), 'exception' => $e]);
            $this->close();
        }

        try {
            return $this->getBrowser()->createPage();
        } catch (BrowserException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->close();

            throw new BrowserException(\sprintf('Unable to open a Chrome tab: %s', $e->getMessage()), 0, $e);
        }
    }

    private function getBrowser(): Browser
    {
        if (null !== $this->browser && !$this->isBrowserAlive()) {
            $this->logger?->warning('The Chrome process is not responding anymore, restarting it.');
            $this->close();
        }

        if (null === $this->browser) {
            $binary = (new ChromeFinder())->find($this->chromeBinary);

            try {
                $this->browser = (new BrowserFactory($binary))->createBrowser($this->buildBrowserOptions());
            } catch (\Throwable $e) {
                throw new BrowserException(\sprintf('Unable to start Chrome "%s": %s', $binary, $e->getMessage()), 0, $e);
            }
        }

        return $this->browser;
    }

    private function isBrowserAlive(): bool
    {
        try {
            return null !== $this->browser && $this->browser->getConnection()->isConnected();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildBrowserOptions(): array
    {
        $defaults = [
            'headless' => true,
            'noSandbox' => $this->noSandbox,
            'ignoreCertificateErrors' => $this->ignoreCertificateErrors,
            'sendSyncDefaultTimeout' => $this->timeout,
            // /dev/shm is 64 MB in Docker by default, large pages crash the renderer
            'customFlags' => ['--disable-dev-shm-usage'],
        ];

        $options = array_merge($defaults, $this->browserOptions);

        if (isset($this->browserOptions['customFlags']) && \is_array($this->browserOptions['customFlags'])) {
            $options['customFlags'] = array_values(array_unique([...$defaults['customFlags'], ...$this->browserOptions['customFlags']]));
        }

        return $options;
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private static function normalizeScreenshotOptions(array $options): array
    {
        $options['width'] = (int) ($options['width'] ?? 800);
        $options['height'] = (int) ($options['height'] ?? 600);
        $options['deviceScaleFactor'] = (float) ($options['deviceScaleFactor'] ?? 1.0);

        if ($options['width'] <= 0 || $options['height'] <= 0) {
            throw new BrowserException(\sprintf('Invalid screenshot size %dx%d.', $options['width'], $options['height']));
        }
        if ($options['deviceScaleFactor'] <= 0) {
            throw new BrowserException(\sprintf('Invalid deviceScaleFactor %s.', $options['deviceScaleFactor']));
        }

        return $options;
    }

    /**
     * @param array<string, mixed> $options normalized screenshot options
     *
     * @return array{width: int, height: int, deviceScaleFactor: float}
     */
    private static function viewport(array $options): array
    {
        return [
            'width' => (int) $options['width'],
            'height' => (int) $options['height'],
            'deviceScaleFactor' => (float) $options['deviceScaleFactor'],
        ];
    }

    private static function shortUrl(string $url): string
    {
        return \strlen($url) > 200 ? substr($url, 0, 200).'...' : $url;
    }
}
