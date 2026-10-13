<?php

namespace Daif\ChromePdfBundle\Tests\Functional\Debug;

use Daif\ChromePdfBundle\Browser\BrowserInterface;

/**
 * Browser double returning fixed payloads, so debug/collector tests do not need Chrome.
 */
final class FakeBrowser implements BrowserInterface
{
    public const PDF = "%PDF-1.4\nfake\n%%EOF";
    public const PNG = "\x89PNG\r\n\x1a\nfake";

    public function htmlToPdf(string $html, array $pdfOptions = [], array $pageOptions = []): string
    {
        return self::PDF;
    }

    public function urlToPdf(string $url, array $pdfOptions = [], array $pageOptions = []): string
    {
        return self::PDF;
    }

    public function htmlToScreenshot(string $html, array $screenshotOptions = [], array $pageOptions = []): string
    {
        return self::PNG;
    }

    public function urlToScreenshot(string $url, array $screenshotOptions = [], array $pageOptions = []): string
    {
        return self::PNG;
    }
}
