<?php

namespace Daif\ChromePdfBundle\Tests\Builder\Pdf;

use Daif\ChromePdfBundle\Builder\Pdf\LibreOfficePdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\MergePdfBuilder;
use Daif\ChromePdfBundle\Builder\Pdf\SplitPdfBuilder;
use Daif\ChromePdfBundle\Engine\BinaryFinder;
use Daif\ChromePdfBundle\Engine\Exiftool;
use Daif\ChromePdfBundle\Engine\LibreOffice;
use Daif\ChromePdfBundle\Engine\PdfPostProcessor;
use Daif\ChromePdfBundle\Engine\Qpdf;
use Daif\ChromePdfBundle\Enumeration\SplitMode;
use Daif\ChromePdfBundle\Processor\InMemoryProcessor;
use Daif\ChromePdfBundle\Tests\Builder\AbstractBuilderTestCase;
use Daif\ChromePdfBundle\Tests\Engine\EngineAvailability;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;

/**
 * Runs generate() with the real engines: produce(), post-processing and ZIP output.
 */
final class EngineGenerationTest extends AbstractBuilderTestCase
{
    private Qpdf $qpdf;

    /** @var list<string> */
    private array $files = [];

    protected function setUp(): void
    {
        parent::setUp();

        $finder = new BinaryFinder();
        $this->qpdf = new Qpdf($finder);
        $libreOffice = new LibreOffice($finder);
        $exiftool = new Exiftool($finder);

        $this->extraServices = [
            'daif_chrome_pdf.engine.qpdf' => $this->qpdf,
            'daif_chrome_pdf.engine.exiftool' => $exiftool,
            'daif_chrome_pdf.engine.libreoffice' => $libreOffice,
            'daif_chrome_pdf.engine.post_processor' => new PdfPostProcessor($this->qpdf, $exiftool, $libreOffice, $this->browser),
        ];
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
    }

    public function testMergeWithBookmarks(): void
    {
        EngineAvailability::binaryOrSkip('qpdf');

        $withOutline = sys_get_temp_dir().'/z_chrome_pdf_outline_'.bin2hex(random_bytes(4)).'.pdf';
        $this->qpdf->addBookmarks(self::FIXTURE_DIR.'/assets/pdf/other_document.pdf', $withOutline, [['title' => 'Inside', 'page' => 1]]);
        $this->files[] = $withOutline;

        $result = $this->createBuilder(MergePdfBuilder::class)
            ->files('pdf/document.pdf', $withOutline)
            ->autoIndexBookmarks()
            ->fileName('merged.pdf')
            ->processor(new InMemoryProcessor())
            ->generate()
        ;

        self::assertSame('application/pdf', $result->getContentType());
        self::assertSame('merged.pdf', $result->getFileName());

        $file = $this->writeResult($result->process());
        $pages = $this->qpdf->pageCount(self::FIXTURE_DIR.'/assets/pdf/document.pdf') + $this->qpdf->pageCount(self::FIXTURE_DIR.'/assets/pdf/other_document.pdf');
        self::assertSame($pages, $this->qpdf->pageCount($file));
        $offset = $this->qpdf->pageCount(self::FIXTURE_DIR.'/assets/pdf/document.pdf');
        self::assertSame([['title' => 'Inside', 'page' => $offset + 1]], $this->qpdf->readBookmarks($file));
    }

    #[RequiresPhpExtension('zip')]
    public function testSplitIntoSeveralFilesReturnsAZip(): void
    {
        EngineAvailability::binaryOrSkip('qpdf');

        $result = $this->createBuilder(SplitPdfBuilder::class)
            ->files('pdf/document.pdf', 'pdf/other_document.pdf')
            ->splitMode(SplitMode::Intervals)
            ->splitSpan('1')
            ->fileName('report.pdf')
            ->processor(new InMemoryProcessor())
            ->generate()
        ;

        self::assertSame('application/zip', $result->getContentType());
        self::assertSame('report.zip', $result->getFileName());

        $zip = new \ZipArchive();
        self::assertTrue($zip->open($this->writeResult($result->process())));
        $pages = $this->qpdf->pageCount(self::FIXTURE_DIR.'/assets/pdf/document.pdf') + $this->qpdf->pageCount(self::FIXTURE_DIR.'/assets/pdf/other_document.pdf');
        self::assertSame($pages, $zip->numFiles);
        $zip->close();
    }

    #[RequiresPhpExtension('zip')]
    public function testOfficeFilesWithoutMergeReturnAZip(): void
    {
        EngineAvailability::binaryOrSkip('soffice');

        $result = $this->createBuilder(LibreOfficePdfBuilder::class)
            ->files('files/content.html', 'files/header.html')
            ->processor(new InMemoryProcessor())
            ->generate()
        ;

        self::assertSame('application/zip', $result->getContentType());
        self::assertSame('documents.zip', $result->getFileName());

        $zip = new \ZipArchive();
        self::assertTrue($zip->open($this->writeResult($result->process())));
        self::assertNotFalse($zip->locateName('content.pdf'));
        self::assertNotFalse($zip->locateName('header.pdf'));
        $zip->close();
    }

    private function writeResult(mixed $content): string
    {
        self::assertIsString($content);
        $file = tempnam(sys_get_temp_dir(), 'chrome_pdf_test_');
        self::assertIsString($file);
        file_put_contents($file, $content);
        $this->files[] = $file;

        return $file;
    }
}
