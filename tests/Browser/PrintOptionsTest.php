<?php

namespace Daif\ChromePdfBundle\Tests\Browser;

use Daif\ChromePdfBundle\Browser\PrintOptions;
use Daif\ChromePdfBundle\Exception\BrowserException;
use PHPUnit\Framework\TestCase;

final class PrintOptionsTest extends TestCase
{
    public function testAllOptionsAreForwarded(): void
    {
        $params = PrintOptions::toCdp([
            'paperWidth' => '8.27',
            'paperHeight' => 11.7,
            'marginTop' => 1,
            'marginBottom' => 0.5,
            'marginLeft' => 0,
            'marginRight' => 0.25,
            'scale' => 0.9,
            'landscape' => true,
            'printBackground' => true,
            'preferCSSPageSize' => false,
            'generateDocumentOutline' => true,
            'generateTaggedPdf' => true,
            'pageRanges' => '1-2',
            'unknown' => 'ignored',
        ]);

        self::assertSame([
            'paperWidth' => 8.27,
            'paperHeight' => 11.7,
            'marginTop' => 1.0,
            'marginBottom' => 0.5,
            'marginLeft' => 0.0,
            'marginRight' => 0.25,
            'scale' => 0.9,
            'landscape' => true,
            'printBackground' => true,
            'preferCSSPageSize' => false,
            'generateDocumentOutline' => true,
            'generateTaggedPdf' => true,
            'pageRanges' => '1-2',
        ], $params);
    }

    public function testHeaderOnlyGetsAnEmptyFooter(): void
    {
        $params = PrintOptions::toCdp(['headerTemplate' => '<p>H</p>']);

        self::assertTrue($params['displayHeaderFooter']);
        self::assertSame('<p>H</p>', $params['headerTemplate']);
        self::assertSame('<span></span>', $params['footerTemplate']);
    }

    public function testFooterOnlyGetsAnEmptyHeader(): void
    {
        $params = PrintOptions::toCdp(['footerTemplate' => '<p>F</p>']);

        self::assertSame('<span></span>', $params['headerTemplate']);
        self::assertSame('<p>F</p>', $params['footerTemplate']);
    }

    public function testNoHeaderFooter(): void
    {
        self::assertArrayNotHasKey('displayHeaderFooter', PrintOptions::toCdp([]));
    }

    public function testSinglePageDropsPageRanges(): void
    {
        $params = PrintOptions::toCdp(['singlePage' => true, 'pageRanges' => '1', 'paperWidth' => 8.5, 'paperHeight' => 11.0]);

        self::assertArrayNotHasKey('pageRanges', $params);
        self::assertArrayNotHasKey('singlePage', $params);
        self::assertSame(8.5, $params['paperWidth']);
    }

    public function testSinglePageLandscapeUsesTheLongEdgeAsWidth(): void
    {
        $params = PrintOptions::toCdp(['singlePage' => true, 'landscape' => true, 'paperWidth' => 8.27, 'paperHeight' => 11.7]);

        self::assertArrayNotHasKey('landscape', $params);
        self::assertSame(11.7, $params['paperWidth']);
    }

    public function testInvalidNumber(): void
    {
        $this->expectException(BrowserException::class);

        PrintOptions::toCdp(['marginTop' => '1cm']);
    }

    public function testSinglePageHeightIncludesMargins(): void
    {
        // 960px = 10in of content
        self::assertEqualsWithDelta(10 + 2 / 96 + 1.5, PrintOptions::singlePageHeight(['marginTop' => 1.0, 'marginBottom' => 0.5], 960), 0.0001);
        self::assertEqualsWithDelta(10 + 2 / 96 + 0.8, PrintOptions::singlePageHeight([], 960), 0.0001, 'Chrome default margins are 0.4in');
    }

    public function testPrintableWidth(): void
    {
        self::assertEqualsWithDelta((8.5 - 0.8) * 96, PrintOptions::printableWidthPx([]), 0.0001);
        self::assertEqualsWithDelta(8 * 96 / 0.5, PrintOptions::printableWidthPx(['paperWidth' => 8.0, 'marginLeft' => 0.0, 'marginRight' => 0.0, 'scale' => 0.5]), 0.0001);
    }

    public function testCountPdfPages(): void
    {
        self::assertSame(3, PrintOptions::countPdfPages("%PDF-1.4\n1 0 obj\n<</Type /Pages /Kids [3 0 R 4 0 R 5 0 R] /Count 3>>\nendobj"));
        self::assertSame(2, PrintOptions::countPdfPages("%PDF-1.4\n<</Type/Page>> <</Type /Page /Parent 1 0 R>>"));
    }
}
