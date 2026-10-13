<?php

namespace Daif\ChromePdfBundle\Tests\Builder;

use Daif\ChromePdfBundle\Builder\AbstractBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\FlattenPdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\HtmlPdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\MergePdfBuilder;
use Daif\ChromePdfBundle\Builder\Screenshot\HtmlScreenshotBuilder;
use Daif\ChromePdfBundle\Builder\ValueObject\EmbeddedFile;
use Daif\ChromePdfBundle\Enumeration\PdfFormat;
use Daif\ChromePdfBundle\Enumeration\RotateAngle;
use Daif\ChromePdfBundle\Enumeration\SplitMode;
use Daif\ChromePdfBundle\Enumeration\StampSource;
use Daif\ChromePdfBundle\Enumeration\WatermarkSource;
use Daif\ChromePdfBundle\Exception\InvalidBuilderConfiguration;
use Daif\ChromePdfBundle\Exception\LogicException;
use Symfony\Component\Filesystem\Path;

final class PostProcessOptionsTest extends AbstractBuilderTestCase
{
    public function testChromiumBuilderWithoutPostProcessOptionsNeedsNoEngine(): void
    {
        $builder = $this->createBuilder(HtmlPdfBuilder::class)->contentRaw('x')->flatten(false)->pdfUniversalAccess(false);

        self::assertSame([], self::postProcessOptions($builder));
        $result = $builder->generate();
        self::assertSame('application/pdf', $result->getContentType());
        self::assertSame(\strlen('%PDF-1.4 fake'), $result->getContentLength());
    }

    public function testScreenshotBuilderHasNoPostProcessOptions(): void
    {
        $builder = $this->createBuilder(HtmlScreenshotBuilder::class)->contentRaw('x')->quality(50);

        self::assertSame([], self::postProcessOptions($builder));
    }

    public function testEveryPostProcessOption(): void
    {
        $assets = realpath(self::FIXTURE_DIR.'/assets');
        $builder = $this->createBuilder(HtmlPdfBuilder::class)
            ->contentRaw('x')
            ->splitMode(SplitMode::Pages)
            ->splitSpan('1-2')
            ->splitUnify()
            ->pdfFormat(PdfFormat::Pdf2b)
            ->pdfUniversalAccess()
            ->metadata(['Author' => 'me'])
            ->addMetadata('Title', 'Doc')
            ->flatten()
            ->rotateAngle(RotateAngle::Rotate90)
            ->rotatePages('1-3')
            ->watermarkSource(WatermarkSource::Text)
            ->watermarkExpression('DRAFT')
            ->watermarkPages('1')
            ->watermarkOptions(['opacity' => 0.2])
            ->stampSource(StampSource::Image)
            ->stampExpression('ignored.png')
            ->stampFile('logo.png')
            ->embedFiles('logo.png', new EmbeddedFile($assets.'/other_logo.png', 'Data', 'image/png'))
            ->userPassword('user')
        ;

        self::assertSame([
            'split' => ['mode' => 'pages', 'span' => '1-2', 'unify' => true],
            'pdfFormat' => 'PDF/A-2b',
            'pdfUniversalAccess' => true,
            'metadata' => ['Title' => 'Doc', 'Author' => 'me'],
            'flatten' => true,
            'rotate' => ['angle' => 90, 'pages' => '1-3'],
            'watermark' => ['source' => 'text', 'expression' => 'DRAFT', 'pages' => '1', 'options' => ['opacity' => 0.2]],
            // files resolved from assets_directory come back canonicalized (forward slashes on Windows)
            'stamp' => ['source' => 'image', 'expression' => Path::canonicalize($assets.'/logo.png'), 'pages' => null, 'options' => []],
            'embeds' => [Path::canonicalize($assets.'/logo.png'), $assets.'/other_logo.png'],
            'encrypt' => ['userPassword' => 'user', 'ownerPassword' => null],
        ], self::postProcessOptions($builder));

        self::assertSame(['other_logo.png' => ['mimeType' => 'image/png', 'relationship' => 'Data']], $builder->getBodyBag()->get('embedsMetadata'));
    }

    public function testResettingOptions(): void
    {
        $builder = $this->createBuilder(HtmlPdfBuilder::class)
            ->contentRaw('x')
            ->splitMode(SplitMode::Pages)->splitMode(null)
            ->pdfFormat(PdfFormat::Pdf1b)->pdfFormat(null)
            ->rotateAngle(RotateAngle::Rotate180)->rotateAngle()
            ->rotatePages('1')->rotatePages(null)
            ->watermarkPages('1')->watermarkPages(null)
            ->userPassword('x')->userPassword(null)
            ->embedFiles('logo.png')->embedFiles()
        ;

        self::assertSame([], self::postProcessOptions($builder));
        self::assertNull($builder->getBodyBag()->get('watermarkPages'));
    }

    public function testFlattenBuilderAlwaysFlattens(): void
    {
        $builder = $this->createBuilder(FlattenPdfBuilder::class)->files('pdf/document.pdf');

        self::assertSame(['flatten' => true], self::postProcessOptions($builder));
    }

    public function testMissingPostProcessorServiceIsReported(): void
    {
        $builder = $this->createBuilder(HtmlPdfBuilder::class)->contentRaw('x')->flatten();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('daif_chrome_pdf.engine.post_processor');

        $builder->generate();
    }

    public function testMissingQpdfServiceIsReported(): void
    {
        $builder = $this->createBuilder(MergePdfBuilder::class)->files('pdf/document.pdf');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('daif_chrome_pdf.engine.qpdf');

        $builder->generate();
    }

    public function testInvalidRanges(): void
    {
        $this->expectException(InvalidBuilderConfiguration::class);

        $this->createBuilder(HtmlPdfBuilder::class)->stampPages('a-b');
    }

    /**
     * @return array<string, mixed>
     */
    public static function postProcessOptions(AbstractBuilder $builder): array
    {
        return (fn (): array => $this->collectPostProcessOptions())->call($builder);
    }
}
