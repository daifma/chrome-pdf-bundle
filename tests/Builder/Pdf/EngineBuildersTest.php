<?php

namespace Daif\ChromePdfBundle\Tests\Builder\Pdf;

use Daif\ChromePdfBundle\Builder\AbstractBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\ConvertPdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\EmbedPdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\EncryptPdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\FlattenPdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\LibreOfficePdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\MergePdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\RotatePdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\SplitPdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\StampPdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\WatermarkPdfBuilder;
use Daif\ChromePdfBundle\Enumeration\ImageResolutionDPI;
use Daif\ChromePdfBundle\Enumeration\InitialView;
use Daif\ChromePdfBundle\Enumeration\Magnification;
use Daif\ChromePdfBundle\Enumeration\PageLayout;
use Daif\ChromePdfBundle\Enumeration\PdfFormat;
use Daif\ChromePdfBundle\Enumeration\SplitMode;
use Daif\ChromePdfBundle\Enumeration\StampSource;
use Daif\ChromePdfBundle\Enumeration\WatermarkSource;
use Daif\ChromePdfBundle\Exception\InvalidBuilderConfiguration;
use Daif\ChromePdfBundle\Exception\MissingRequiredFieldException;
use Daif\ChromePdfBundle\Tests\Builder\AbstractBuilderTestCase;
use Daif\ChromePdfBundle\Tests\Builder\PostProcessOptionsTest;
use PHPUnit\Framework\Attributes\DataProvider;

final class EngineBuildersTest extends AbstractBuilderTestCase
{
    /**
     * @return iterable<string, array{class-string<AbstractBuilder>, \Closure, string}>
     */
    public static function invalidPayloads(): iterable
    {
        $noop = static fn (AbstractBuilder $builder): mixed => null;
        $pdf = static fn (object $builder): mixed => $builder->files('pdf/document.pdf'); // @phpstan-ignore method.notFound

        yield 'convert files' => [ConvertPdfBuilder::class, static fn (ConvertPdfBuilder $b): mixed => $b->pdfFormat(PdfFormat::Pdf1b), 'At least one PDF file is required.'];
        yield 'encrypt files' => [EncryptPdfBuilder::class, static fn (EncryptPdfBuilder $b): mixed => $b->userPassword('x'), 'At least one PDF file is required.'];
        yield 'flatten files' => [FlattenPdfBuilder::class, $noop, 'At least one PDF file is required.'];
        yield 'merge files' => [MergePdfBuilder::class, $noop, 'At least one PDF file is required.'];
        yield 'office files' => [LibreOfficePdfBuilder::class, $noop, 'At least one office file is required.'];
        yield 'convert format' => [ConvertPdfBuilder::class, $pdf, 'At least "pdfa" or "pdfua" must be provided.'];
        yield 'embed embeds' => [EmbedPdfBuilder::class, $pdf, 'At least one embed file is required.'];
        yield 'encrypt password' => [EncryptPdfBuilder::class, $pdf, 'At least userPassword must be provided.'];
        yield 'rotate angle' => [RotatePdfBuilder::class, $pdf, 'Field "rotateAngle" must be provided.'];
        yield 'split mode' => [SplitPdfBuilder::class, $pdf, 'Field "splitMode" must be provided.'];
        yield 'split span' => [SplitPdfBuilder::class, static fn (SplitPdfBuilder $b): mixed => $b->files('pdf/document.pdf')->splitMode(SplitMode::Pages), 'Field "splitSpan" must be provided.'];
        yield 'stamp source' => [StampPdfBuilder::class, $pdf, 'Field "stampSource" must be provided.'];
        yield 'stamp expression' => [StampPdfBuilder::class, static fn (StampPdfBuilder $b): mixed => $b->files('pdf/document.pdf')->stampSource(StampSource::Text), 'Field "stampExpression" must be provided.'];
        yield 'stamp file' => [StampPdfBuilder::class, static fn (StampPdfBuilder $b): mixed => $b->files('pdf/document.pdf')->stampSource(StampSource::Pdf)->stampExpression('x'), 'A stamp file is required when source is "image" or "pdf".'];
        yield 'watermark source' => [WatermarkPdfBuilder::class, $pdf, 'Field "watermarkSource" must be provided.'];
        yield 'watermark expression' => [WatermarkPdfBuilder::class, static fn (WatermarkPdfBuilder $b): mixed => $b->files('pdf/document.pdf')->watermarkSource(WatermarkSource::Text), 'Field "watermarkExpression" must be provided.'];
        yield 'watermark file' => [WatermarkPdfBuilder::class, static fn (WatermarkPdfBuilder $b): mixed => $b->files('pdf/document.pdf')->watermarkSource(WatermarkSource::Image)->watermarkExpression('x'), 'A watermark file is required when source is "image" or "pdf".'];
    }

    /**
     * @param class-string<AbstractBuilder> $class
     */
    #[DataProvider('invalidPayloads')]
    public function testRequiredFields(string $class, \Closure $configure, string $message): void
    {
        $builder = $this->createBuilder($class);
        $configure($builder);

        $this->expectException(MissingRequiredFieldException::class);
        $this->expectExceptionMessage($message);

        $builder->generate();
    }

    public function testFilesExtensionIsValidated(): void
    {
        $this->expectException(InvalidBuilderConfiguration::class);

        $this->createBuilder(MergePdfBuilder::class)->files('logo.png');
    }

    public function testOfficeAcceptsOfficeExtensions(): void
    {
        $builder = $this->createBuilder(LibreOfficePdfBuilder::class)->files('files/content.html', 'pdf/document.pdf');

        self::assertCount(2, $builder->getBodyBag()->get('files'));

        $this->expectException(InvalidBuilderConfiguration::class);
        $builder->files('file.md');
    }

    public function testOfficeInvalidCombinations(): void
    {
        $builder = $this->createBuilder(LibreOfficePdfBuilder::class)->files('pdf/document.pdf')->splitMode(SplitMode::Intervals)->splitUnify();

        try {
            $builder->generate();
            self::fail('splitUnify with intervals must be rejected.');
        } catch (InvalidBuilderConfiguration $e) {
            self::assertStringContainsString('splitUnify', $e->getMessage());
        }

        $builder->splitMode(SplitMode::Pages)->zoom(50);

        $this->expectException(InvalidBuilderConfiguration::class);
        $this->expectExceptionMessage('"zoom" can only be set');
        $builder->generate();
    }

    public function testOfficeFilterOptions(): void
    {
        $builder = $this->createBuilder(LibreOfficePdfBuilder::class)
            ->files('pdf/document.pdf')
            ->password('secret')
            ->landscape()
            ->nativePageRanges('1-2')
            ->doNotExportFormFields()
            ->allowDuplicateFieldNames()
            ->doNotExportBookmarks()
            ->exportBookmarksToPdfDestination()
            ->exportPlaceholders()
            ->exportNotes()
            ->exportNotesPages()
            ->exportOnlyNotesPages()
            ->exportNotesInMargin()
            ->convertOooTargetToPdfTarget()
            ->exportLinksRelativeFsys()
            ->exportHiddenSlides()
            ->skipEmptyPages()
            ->addOriginalDocumentAsStream()
            ->singlePageSheets()
            ->losslessImageCompression()
            ->quality(80)
            ->reduceImageResolution()
            ->maxImageResolution(ImageResolutionDPI::DPI300)
            ->doNotUpdateIndexes()
            ->watermarkText('DRAFT')
            ->watermarkColor('#FF0000')
            ->watermarkFontHeight(40)
            ->watermarkRotateAngle(450)
            ->watermarkFontName('Liberation Sans')
            ->tiledWatermarkText('COPY')
            ->initialView(InitialView::Thumbnails)
            ->initialPage(2)
            ->magnification(Magnification::UseZoomValue)
            ->zoom(75)
            ->pageLayout(PageLayout::TwoColumns)
            ->firstPageOnLeft()
            ->resizeWindowToInitialPage()
            ->centerWindow()
            ->openInFullScreenMode()
            ->displayPDFDocumentTitle()
            ->hideViewerMenubar()
            ->hideViewerToolbar()
            ->hideViewerWindowControls()
            ->useTransitionEffects()
            ->openBookmarkLevels(-1)
            ->pdfFormat(PdfFormat::Pdf3b)
            ->pdfUniversalAccess()
        ;

        $options = (fn (): array => $this->collectFilterOptions())->call($builder);

        self::assertSame([
            'ExportFormFields' => false,
            'AllowDuplicateFieldNames' => true,
            'ExportBookmarks' => false,
            'ExportBookmarksToPDFDestination' => true,
            'ExportPlaceholders' => true,
            'ExportNotes' => true,
            'ExportNotesPages' => true,
            'ExportOnlyNotesPages' => true,
            'ExportNotesInMargin' => true,
            'ConvertOOoTargetToPDFTarget' => true,
            'ExportLinksRelativeFsys' => true,
            'ExportHiddenSlides' => true,
            'IsSkipEmptyPages' => true,
            'IsAddStream' => true,
            'SinglePageSheets' => true,
            'UseLosslessCompression' => true,
            'Quality' => 80,
            'ReduceImageResolution' => true,
            'MaxImageResolution' => 300,
            'PageRange' => '1-2',
            'Watermark' => 'DRAFT',
            'WatermarkColor' => 0xFF0000,
            'WatermarkFontHeight' => 40,
            'WatermarkRotateAngle' => 450,
            'WatermarkFontName' => 'Liberation Sans',
            'TiledWatermark' => 'COPY',
            'InitialView' => 2,
            'InitialPage' => 2,
            'Magnification' => 4,
            'Zoom' => 75,
            'PageLayout' => 3,
            'FirstPageOnLeft' => true,
            'ResizeWindowToInitialPage' => true,
            'CenterWindow' => true,
            'OpenInFullScreenMode' => true,
            'DisplayPDFDocumentTitle' => true,
            'HideViewerMenubar' => true,
            'HideViewerToolbar' => true,
            'HideViewerWindowControls' => true,
            'UseTransitionEffects' => true,
            'OpenBookmarkLevels' => -1,
            'SelectPdfVersion' => 3,
            'PDFUACompliance' => true,
        ], $options);

        // Without merge, LibreOffice exports PDF/A itself: the post-processing does not convert again.
        self::assertSame([], PostProcessOptionsTest::postProcessOptions($builder));
    }

    public function testOfficeMergeConvertsToPdfFormatAfterMerging(): void
    {
        $builder = $this->createBuilder(LibreOfficePdfBuilder::class)
            ->files('pdf/document.pdf', 'pdf/other_document.pdf')
            ->merge()
            ->pdfFormat(PdfFormat::Pdf1b)
        ;

        self::assertArrayNotHasKey('SelectPdfVersion', (fn (): array => $this->collectFilterOptions())->call($builder));
        self::assertSame(['pdfFormat' => 'PDF/A-1b', 'pdfUniversalAccess' => false], PostProcessOptionsTest::postProcessOptions($builder));
    }

    public function testInvalidWatermarkColor(): void
    {
        $this->expectException(InvalidBuilderConfiguration::class);

        $this->createBuilder(LibreOfficePdfBuilder::class)->watermarkColor('red');
    }

    public function testMergeBookmarks(): void
    {
        $builder = $this->createBuilder(MergePdfBuilder::class)
            ->files('pdf/other_document.pdf', 'pdf/document.pdf')
            ->bookmarks([
                'document.pdf' => [['title' => 'Intro', 'page' => 1, 'children' => [['title' => 'Detail', 'page' => 2]]]],
                'other_document.pdf' => [['title' => 'Other', 'page' => 1]],
            ])
        ;

        // page counts as computed by produce(), in merge order
        (fn () => $this->pageCounts = ['pdf/document.pdf' => 2, 'pdf/other_document.pdf' => 3])->call($builder);

        self::assertSame([
            ['title' => 'Intro', 'page' => 1, 'children' => [['title' => 'Detail', 'page' => 2]]],
            ['title' => 'Other', 'page' => 3],
        ], PostProcessOptionsTest::postProcessOptions($builder)['bookmarks']);

        // bookmarks read from the input files by produce()
        (fn () => $this->existingBookmarks = ['pdf/document.pdf' => [['title' => 'Own', 'page' => 2]], 'pdf/other_document.pdf' => [['title' => 'Own 2', 'page' => 1, 'children' => [['title' => 'Deep', 'page' => 3]]]]])->call($builder);
        $builder->autoIndexBookmarks();
        self::assertSame([
            ['title' => 'Own', 'page' => 2],
            ['title' => 'Intro', 'page' => 1, 'children' => [['title' => 'Detail', 'page' => 2]]],
            ['title' => 'Own 2', 'page' => 3, 'children' => [['title' => 'Deep', 'page' => 5]]],
            ['title' => 'Other', 'page' => 3],
        ], PostProcessOptionsTest::postProcessOptions($builder)['bookmarks']);

        $builder->bookmarks([])->addBookmark('End', 5);
        self::assertSame([
            ['title' => 'Own', 'page' => 2],
            ['title' => 'Own 2', 'page' => 3, 'children' => [['title' => 'Deep', 'page' => 5]]],
            ['title' => 'End', 'page' => 5],
        ], PostProcessOptionsTest::postProcessOptions($builder)['bookmarks']);
    }

    public function testMergeSortsFilesAlphanumerically(): void
    {
        $sorted = (new \ReflectionMethod(MergePdfBuilder::class, 'sortFiles'))->invoke(null, ['b10.pdf' => 'x', 'B2.pdf' => 'y', 'a.pdf' => 'z']);

        self::assertIsArray($sorted);
        self::assertSame(['a.pdf', 'B2.pdf', 'b10.pdf'], array_keys($sorted));
    }

    public function testInvalidBookmarks(): void
    {
        $this->expectException(InvalidBuilderConfiguration::class);

        $this->createBuilder(MergePdfBuilder::class)->bookmarks([['title' => 'x', 'page' => 0]]);
    }
}
