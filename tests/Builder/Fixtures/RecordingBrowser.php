<?php

namespace Daif\ChromePdfBundle\Tests\Builder\Fixtures;

use Daif\ChromePdfBundle\Browser\BrowserInterface;

/**
 * Records the arguments of the last call instead of driving Chrome.
 */
final class RecordingBrowser implements BrowserInterface
{
    public string|null $method = null;
    public string|null $source = null;

    /** @var array<string, mixed> */
    public array $options = [];

    /** @var array<string, mixed> */
    public array $pageOptions = [];

    /** @var array<string, string> contents of the assets, captured during the call (they may be temporary files) */
    public array $assetContents = [];

    /** @var (\Closure(): void)|null */
    public \Closure|null $onCall = null;

    public function htmlToPdf(string $html, array $pdfOptions = [], array $pageOptions = []): string
    {
        return $this->record(__FUNCTION__, $html, $pdfOptions, $pageOptions, '%PDF-1.4 fake');
    }

    public function urlToPdf(string $url, array $pdfOptions = [], array $pageOptions = []): string
    {
        return $this->record(__FUNCTION__, $url, $pdfOptions, $pageOptions, '%PDF-1.4 fake');
    }

    public function htmlToScreenshot(string $html, array $screenshotOptions = [], array $pageOptions = []): string
    {
        return $this->record(__FUNCTION__, $html, $screenshotOptions, $pageOptions, 'fake-image');
    }

    public function urlToScreenshot(string $url, array $screenshotOptions = [], array $pageOptions = []): string
    {
        return $this->record(__FUNCTION__, $url, $screenshotOptions, $pageOptions, 'fake-image');
    }

    /**
     * @param array<string, mixed> $options
     * @param array<string, mixed> $pageOptions
     */
    private function record(string $method, string $source, array $options, array $pageOptions, string $result): string
    {
        $this->method = $method;
        $this->source = $source;
        $this->options = $options;
        $this->pageOptions = $pageOptions;
        $this->assetContents = [];

        foreach ($pageOptions['assets'] ?? [] as $key => $asset) {
            if ($asset instanceof \SplFileInfo && is_file($asset->getPathname())) {
                $this->assetContents[$asset->getFilename()] = (string) file_get_contents($asset->getPathname());
            }
        }

        if (null !== $this->onCall) {
            ($this->onCall)();
        }

        return $result;
    }
}
