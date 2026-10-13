<?php

namespace Daif\ChromePdfBundle\Tests\Builder\Pdf;

use Daif\ChromePdfBundle\Builder\Pdf\HtmlPdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\UrlPdfBuilder;
use Daif\ChromePdfBundle\Enumeration\PaperSize;
use Daif\ChromePdfBundle\Enumeration\Unit;
use Daif\ChromePdfBundle\Exception\InvalidBuilderConfiguration;
use Daif\ChromePdfBundle\Exception\MissingRequiredFieldException;
use Daif\ChromePdfBundle\Exception\PartRenderingException;
use Daif\ChromePdfBundle\Tests\Builder\AbstractBuilderTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\HeaderUtils;

final class PdfBuilderTest extends AbstractBuilderTestCase
{
    public function testEveryPdfOption(): void
    {
        $this->createBuilder(HtmlPdfBuilder::class)
            ->contentRaw('x')
            ->paperSize(21, 29.7, Unit::Centimeters)
            ->margins(72, 96, 6, 25.4, Unit::Points)
            ->marginBottom(96, Unit::Pixels)
            ->marginLeft(6, Unit::Picas)
            ->marginRight(25.4, Unit::Millimeters)
            ->scale(1.5)
            ->landscape()
            ->printBackground()
            ->preferCssPageSize()
            ->singlePage()
            ->generateDocumentOutline()
            ->generateTaggedPdf(false)
            ->nativePageRanges('1-5, 8')
            ->omitBackground()
            ->generate()
        ;

        self::assertSame('htmlToPdf', $this->browser->method);
        $options = $this->browser->options;
        self::assertEqualsWithDelta(21 / 2.54, $options['paperWidth'], 1e-9);
        self::assertEqualsWithDelta(29.7 / 2.54, $options['paperHeight'], 1e-9);
        self::assertEqualsWithDelta(1.0, $options['marginTop'], 1e-9);
        self::assertEqualsWithDelta(1.0, $options['marginBottom'], 1e-9);
        self::assertEqualsWithDelta(1.0, $options['marginLeft'], 1e-9);
        self::assertEqualsWithDelta(1.0, $options['marginRight'], 1e-9);
        unset($options['paperWidth'], $options['paperHeight'], $options['marginTop'], $options['marginBottom'], $options['marginLeft'], $options['marginRight']);

        self::assertSame([
            'scale' => 1.5,
            'landscape' => true,
            'printBackground' => true,
            'preferCSSPageSize' => true,
            'singlePage' => true,
            'generateDocumentOutline' => true,
            'generateTaggedPdf' => false,
            'pageRanges' => '1-5, 8',
        ], $options);

        // omitBackground is a page option (default background override), not a printToPDF one.
        self::assertTrue($this->browser->pageOptions['omitBackground']);
    }

    public function testPaperStandardSize(): void
    {
        $this->createBuilder(HtmlPdfBuilder::class)->contentRaw('x')->paperStandardSize(PaperSize::A4)->generate();

        self::assertEqualsWithDelta(PaperSize::A4->width(), $this->browser->options['paperWidth'], 1e-9);
        self::assertEqualsWithDelta(PaperSize::A4->height(), $this->browser->options['paperHeight'], 1e-9);
    }

    public function testDefaultPdfOptionsAreEmpty(): void
    {
        $this->createBuilder(HtmlPdfBuilder::class)->contentRaw('x')->generate();

        self::assertSame([], $this->browser->options);
    }

    /**
     * @return iterable<string, array{string|int|float, float}>
     */
    public static function rawLengths(): iterable
    {
        yield 'picas' => ['6pc', 1.0];
        yield 'points' => ['72pt', 1.0];
        yield 'pixels' => ['96px', 1.0];
        yield 'millimeters' => ['25.4mm', 1.0];
        yield 'centimeters' => ['2.54cm', 1.0];
        yield 'inches' => ['2in', 2.0];
        yield 'no unit' => ['3', 3.0];
        yield 'int' => [4, 4.0];
        yield 'spaces' => [' 12 pt ', 1 / 6];
    }

    /**
     * Values set through the bundle configuration reach the body bag as strings with a unit.
     */
    #[DataProvider('rawLengths')]
    public function testRawLengthsAreConvertedIntoInches(string|int|float $raw, float $expected): void
    {
        $builder = $this->createBuilder(HtmlPdfBuilder::class)->contentRaw('x');
        $builder->getBodyBag()->set('marginTop', $raw);
        $builder->generate();

        self::assertEqualsWithDelta($expected, $this->browser->options['marginTop'], 1e-9);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidLengths(): iterable
    {
        yield 'garbage' => ['garbage'];
        yield 'unknown unit' => ['12km'];
        yield 'negative' => ['-1in'];
        yield 'array' => [[1]];
    }

    #[DataProvider('invalidLengths')]
    public function testInvalidRawLengthsAreRejected(mixed $raw): void
    {
        $builder = $this->createBuilder(HtmlPdfBuilder::class)->contentRaw('x');
        $builder->getBodyBag()->set('paperWidth', $raw);

        $this->expectException(InvalidBuilderConfiguration::class);
        $this->expectExceptionMessage('Invalid "paperWidth" value');

        $builder->generate();
    }

    public function testNegativeLengthIsRejected(): void
    {
        $this->expectException(InvalidBuilderConfiguration::class);

        $this->createBuilder(HtmlPdfBuilder::class)->marginTop(-1);
    }

    public function testZeroPaperWidthIsRejected(): void
    {
        $this->expectException(InvalidBuilderConfiguration::class);

        $this->createBuilder(HtmlPdfBuilder::class)->paperWidth(0);
    }

    public function testScaleIsAFactorNotALength(): void
    {
        $builder = $this->createBuilder(HtmlPdfBuilder::class)->contentRaw('x');
        $builder->getBodyBag()->set('scale', '0.5');
        $builder->generate();

        self::assertSame(0.5, $this->browser->options['scale']);
    }

    public function testScaleOutOfRangeIsRejected(): void
    {
        $this->expectException(InvalidBuilderConfiguration::class);

        $this->createBuilder(HtmlPdfBuilder::class)->scale(2.5);
    }

    public function testInvalidPageRangesAreRejected(): void
    {
        $this->expectException(InvalidBuilderConfiguration::class);

        $this->createBuilder(HtmlPdfBuilder::class)->nativePageRanges('first page');
    }

    public function testHeaderOnlyGetsAnEmptyFooter(): void
    {
        $this->createBuilder(HtmlPdfBuilder::class)->contentRaw('x')->headerRaw('<h1>Header</h1>')->generate();

        self::assertSame('<h1>Header</h1>', $this->browser->options['headerTemplate']);
        self::assertSame('<span></span>', $this->browser->options['footerTemplate']);
    }

    public function testFooterOnlyGetsAnEmptyHeader(): void
    {
        $this->createBuilder(HtmlPdfBuilder::class)->contentRaw('x')->footerRaw('<p>Footer</p>')->generate();

        self::assertSame('<span></span>', $this->browser->options['headerTemplate']);
        self::assertSame('<p>Footer</p>', $this->browser->options['footerTemplate']);
    }

    public function testHeaderAndFooterFromTemplatesAndFiles(): void
    {
        $this->createBuilder(HtmlPdfBuilder::class)
            ->content('content.html.twig', ['name' => 'Body'])
            ->header('header.html.twig', ['name' => 'Header'])
            ->footerFile('files/footer.html')
            ->generate()
        ;

        self::assertStringContainsString('<h1>Hello Body!</h1>', (string) $this->browser->source);
        self::assertStringContainsString('<h1>Hello Header!</h1>', $this->browser->options['headerTemplate']);
        self::assertStringContainsString('<h1>Hello Footer</h1>', $this->browser->options['footerTemplate']);
    }

    public function testHeaderAssetsAreInlinedAsDataUris(): void
    {
        $this->createBuilder(HtmlPdfBuilder::class)
            ->contentRaw('x')
            ->header('header_with_asset.html.twig', ['name' => 'Header'])
            ->footer('footer_with_font.html.twig')
            ->generate()
        ;

        $dataUri = 'data:image/png;base64,'.base64_encode((string) file_get_contents(self::FIXTURE_DIR.'/assets/logo.png'));
        self::assertStringContainsString('<img src="'.$dataUri.'" />', $this->browser->options['headerTemplate']);
        self::assertStringContainsString('src: url("'.$dataUri.'");', $this->browser->options['footerTemplate']);
        // Inlined assets are not copied next to the document.
        self::assertArrayNotHasKey('assets', $this->browser->pageOptions);
    }

    public function testBodyAssetsAreCopiedAndReferencedByBasename(): void
    {
        $this->createBuilder(HtmlPdfBuilder::class)->content('content_with_asset.html.twig')->generate();

        self::assertStringContainsString('<img src="logo.png" />', (string) $this->browser->source);
        self::assertStringContainsString('src: url("logo.png");', (string) $this->browser->source);
        self::assertSame(['logo.png'], array_keys($this->browser->pageOptions['assets']));
    }

    public function testContentFile(): void
    {
        $this->createBuilder(HtmlPdfBuilder::class)->contentFile('files/content.html')->generate();

        self::assertStringContainsString('<h1>Hello world!</h1>', (string) $this->browser->source);
    }

    public function testContentFileWithWindowsSeparators(): void
    {
        $this->createBuilder(HtmlPdfBuilder::class)->contentFile('files\\content.html')->generate();

        self::assertStringContainsString('<h1>Hello world!</h1>', (string) $this->browser->source);
    }

    public function testMissingContentFileIsRejected(): void
    {
        $this->expectException(PartRenderingException::class);

        $this->createBuilder(HtmlPdfBuilder::class)->contentFile('files/missing.html');
    }

    public function testInvalidTemplateIsRejected(): void
    {
        $this->expectException(PartRenderingException::class);
        $this->expectExceptionMessage('Could not render template "invalid.html.twig" into PDF part "index.html".');

        $this->createBuilder(HtmlPdfBuilder::class)->content('invalid.html.twig');
    }

    public function testContentIsRequired(): void
    {
        $this->expectException(MissingRequiredFieldException::class);

        $this->createBuilder(HtmlPdfBuilder::class)->generate();
    }

    public function testResultHasPdfContentType(): void
    {
        $result = $this->createBuilder(HtmlPdfBuilder::class)
            ->contentRaw('x')
            ->fileName('report.pdf', HeaderUtils::DISPOSITION_ATTACHMENT)
            ->generate()
        ;

        self::assertSame('application/pdf', $result->getContentType());
        self::assertSame('report.pdf', $result->getFileName());

        $response = $result->stream();
        self::assertSame('application/pdf', $response->headers->get('Content-Type'));
        self::assertSame('attachment; filename=report.pdf', $response->headers->get('Content-Disposition'));
    }

    public function testUrlIsRequired(): void
    {
        $this->expectException(MissingRequiredFieldException::class);

        $this->createBuilder(UrlPdfBuilder::class)->generate();
    }

    public function testUrlAndRouteAreExclusive(): void
    {
        $this->expectException(MissingRequiredFieldException::class);

        $this->createBuilder(UrlPdfBuilder::class)->url('https://example.com')->route('home')->generate();
    }

    public function testRouteRequiresARouter(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('UrlGenerator is required');

        $this->createBuilder(UrlPdfBuilder::class)->route('home')->generate();
    }
}
