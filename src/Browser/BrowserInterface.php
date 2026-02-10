<?php

namespace Daif\ChromePdfBundle\Browser;

interface BrowserInterface
{
    /**
     * @param array<string, mixed> $pdfOptions  Options for Page.printToPDF CDP command
     * @param array<string, mixed> $pageOptions Options for page setup (cookies, headers, media, wait, etc.)
     *
     * @return string Raw PDF binary data
     */
    public function htmlToPdf(string $html, array $pdfOptions = [], array $pageOptions = []): string;

    /**
     * @param array<string, mixed> $pdfOptions  Options for Page.printToPDF CDP command
     * @param array<string, mixed> $pageOptions Options for page setup (cookies, headers, media, wait, etc.)
     *
     * @return string Raw PDF binary data
     */
    public function urlToPdf(string $url, array $pdfOptions = [], array $pageOptions = []): string;

    /**
     * @param array<string, mixed> $screenshotOptions Options for Page.captureScreenshot CDP command
     * @param array<string, mixed> $pageOptions       Options for page setup (cookies, headers, media, wait, etc.)
     *
     * @return string Raw screenshot binary data
     */
    public function htmlToScreenshot(string $html, array $screenshotOptions = [], array $pageOptions = []): string;

    /**
     * @param array<string, mixed> $screenshotOptions Options for Page.captureScreenshot CDP command
     * @param array<string, mixed> $pageOptions       Options for page setup (cookies, headers, media, wait, etc.)
     *
     * @return string Raw screenshot binary data
     */
    public function urlToScreenshot(string $url, array $screenshotOptions = [], array $pageOptions = []): string;
}
