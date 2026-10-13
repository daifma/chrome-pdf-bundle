<?php

namespace Daif\ChromePdfBundle\Tests\Functional;

use Daif\ChromePdfBundle\Builder\Result\ChromePdfFileResult;
use Daif\ChromePdfBundle\ChromePdfFacadeInterface;
use Daif\ChromePdfBundle\ChromePdfInterface;
use Daif\ChromePdfBundle\Engine\BinaryFinder;
use Daif\ChromePdfBundle\Engine\Exiftool;
use Daif\ChromePdfBundle\Engine\Qpdf;
use Daif\ChromePdfBundle\Enumeration\PdfFormat;
use Daif\ChromePdfBundle\Enumeration\RotateAngle;
use Daif\ChromePdfBundle\Enumeration\SplitMode;
use Daif\ChromePdfBundle\Enumeration\StampSource;
use Daif\ChromePdfBundle\Enumeration\WatermarkSource;
use Daif\ChromePdfBundle\Processor\InMemoryProcessor;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * Kernel level smoke tests of the PDF builders backed by qpdf, exiftool and LibreOffice.
 */
#[Group('engine')]
final class EngineTest extends AbstractChromePdfWebTestCase
{
    private const DOCUMENT = 'pdf/document.pdf';
    private const OTHER_DOCUMENT = 'pdf/other_document.pdf';
    private const FIXTURES = __DIR__.'/../Fixtures/assets/';

    private string $workDir;

    protected function setUp(): void
    {
        self::bootKernel(['test_case' => 'Engine']);

        $this->workDir = self::getTmpDir().'/work';
        (new Filesystem())->mkdir($this->workDir);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        foreach (['CHROME_PDF_TEST_BINARY', 'CHROME_PDF_TEST_NO_SANDBOX'] as $name) {
            unset($_SERVER[$name], $_ENV[$name]);
            putenv($name);
        }
    }

    public function testMerge(): void
    {
        EngineAvailability::requireOrSkip('qpdf');

        $result = $this->pdf()->merge()
            ->files(self::DOCUMENT, self::OTHER_DOCUMENT)
            ->generate()
        ;

        $file = $this->save($result, 'merged.pdf');
        self::assertSame(
            $this->pageCount(self::FIXTURES.self::DOCUMENT) + $this->pageCount(self::FIXTURES.self::OTHER_DOCUMENT),
            $this->pageCount($file),
        );
    }

    public function testSplitIntoSeveralFilesReturnsAZip(): void
    {
        EngineAvailability::requireOrSkip('qpdf');

        $merged = $this->save($this->pdf()->merge()->files(self::DOCUMENT, self::OTHER_DOCUMENT)->generate(), 'to-split.pdf');
        $pages = $this->pageCount($merged);
        self::assertGreaterThan(1, $pages);

        $result = $this->pdf()->split()
            ->files($merged)
            ->splitMode(SplitMode::Intervals)
            ->splitSpan('1')
            ->generate()
        ;

        self::assertSame('application/zip', $result->getContentType());
        $zipFile = $this->save($result, 'split.zip');

        $zip = new \ZipArchive();
        self::assertTrue($zip->open($zipFile));
        try {
            self::assertSame($pages, $zip->numFiles);
            $first = $zip->getFromIndex(0);
            self::assertIsString($first);
            self::assertStringStartsWith('%PDF-', $first);
        } finally {
            $zip->close();
        }
    }

    public function testSplitWithUnifyReturnsASinglePdf(): void
    {
        EngineAvailability::requireOrSkip('qpdf');

        $merged = $this->save($this->pdf()->merge()->files(self::DOCUMENT, self::OTHER_DOCUMENT)->generate(), 'to-unify.pdf');

        $result = $this->pdf()->split()
            ->files($merged)
            ->splitMode(SplitMode::Pages)
            ->splitSpan('1')
            ->splitUnify()
            ->generate()
        ;

        self::assertSame('application/pdf', $result->getContentType());
        self::assertSame(1, $this->pageCount($this->save($result, 'unified.pdf')));
    }

    public function testRotate(): void
    {
        EngineAvailability::requireOrSkip('qpdf');

        $result = $this->pdf()->rotate()
            ->files(self::DOCUMENT)
            ->rotateAngle(RotateAngle::Rotate90)
            ->generate()
        ;

        self::assertMatchesRegularExpression('#/Rotate\s+90\b#', $this->qdf($this->save($result, 'rotated.pdf')));
    }

    public function testFlatten(): void
    {
        EngineAvailability::requireOrSkip('qpdf');

        $result = $this->pdf()->flatten()->files(self::DOCUMENT)->generate();

        self::assertSame($this->pageCount(self::FIXTURES.self::DOCUMENT), $this->pageCount($this->save($result, 'flat.pdf')));
    }

    public function testEncrypt(): void
    {
        EngineAvailability::requireOrSkip('qpdf');

        $result = $this->pdf()->encrypt()
            ->files(self::DOCUMENT)
            ->userPassword('secret')
            ->generate()
        ;

        $file = $this->save($result, 'encrypted.pdf');
        $process = new Process([$this->binary('qpdf'), '--requires-password', $file]);
        $process->run();
        // qpdf exit code 0: a password is required.
        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
    }

    public function testEmbed(): void
    {
        EngineAvailability::requireOrSkip('qpdf');

        $result = $this->pdf()->embed()
            ->files(self::DOCUMENT)
            ->embedFiles('file.md')
            ->generate()
        ;

        $process = new Process([$this->binary('qpdf'), '--list-attachments', $this->save($result, 'embedded.pdf')]);
        $process->mustRun();
        self::assertStringContainsString('file.md', $process->getOutput());
    }

    public function testWatermarkWithAPdf(): void
    {
        EngineAvailability::requireOrSkip('qpdf');

        $result = $this->pdf()->watermark()
            ->files(self::DOCUMENT)
            ->watermarkSource(WatermarkSource::Pdf)
            ->watermarkExpression(self::OTHER_DOCUMENT)
            ->watermarkFile(self::OTHER_DOCUMENT)
            ->generate()
        ;

        $file = $this->save($result, 'watermarked.pdf');
        self::assertSame($this->pageCount(self::FIXTURES.self::DOCUMENT), $this->pageCount($file));
        self::assertGreaterThan(filesize(self::FIXTURES.self::DOCUMENT), filesize($file));
    }

    public function testStampWithAText(): void
    {
        EngineAvailability::requireOrSkip('qpdf');
        $this->useChrome();

        $result = $this->pdf()->stamp()
            ->files(self::DOCUMENT)
            ->stampSource(StampSource::Text)
            ->stampExpression('APPROVED')
            ->generate()
        ;

        $file = $this->save($result, 'stamped.pdf');
        self::assertSame($this->pageCount(self::FIXTURES.self::DOCUMENT), $this->pageCount($file));
        self::assertGreaterThan(filesize(self::FIXTURES.self::DOCUMENT), filesize($file));
    }

    public function testMetadataAreWrittenWithExiftool(): void
    {
        EngineAvailability::requireOrSkip('qpdf', 'exiftool');

        $result = $this->pdf()->merge()
            ->files(self::DOCUMENT)
            ->metadata(['Author' => 'Chrome PDF Bundle', 'Title' => 'Merged'])
            ->generate()
        ;

        $metadata = $this->exiftool()->readMetadata($this->save($result, 'metadata.pdf'));
        self::assertSame('Chrome PDF Bundle', $metadata['Author'] ?? null);
        self::assertSame('Merged', $metadata['Title'] ?? null);
    }

    public function testConvertToPdfA(): void
    {
        EngineAvailability::requireOrSkip('qpdf', 'exiftool', 'soffice');

        $result = $this->pdf()->convert()
            ->files(self::DOCUMENT)
            ->pdfFormat(PdfFormat::Pdf2b)
            ->generate()
        ;

        $metadata = $this->exiftool()->readMetadata($this->save($result, 'pdfa.pdf'));
        self::assertEquals(2, $metadata['Part'] ?? null, 'The XMP pdfaid:part must be 2.');
    }

    public function testOffice(): void
    {
        EngineAvailability::requireOrSkip('soffice');

        $input = $this->workDir.'/letter.txt';
        file_put_contents($input, "Hello from LibreOffice.\n");

        $result = $this->pdf()->office()
            ->files($input)
            ->generate()
        ;

        self::assertSame('application/pdf', $result->getContentType());
        self::assertGreaterThanOrEqual(1, $this->pageCount($this->save($result, 'office.pdf')));
    }

    private function pdf(): ChromePdfInterface
    {
        $facade = self::getContainer()->get(ChromePdfFacadeInterface::class);
        self::assertInstanceOf(ChromePdfFacadeInterface::class, $facade);

        return $facade->pdf();
    }

    /**
     * @param ChromePdfFileResult<mixed> $result
     */
    private function save(ChromePdfFileResult $result, string $name): string
    {
        $content = $result->processor(new InMemoryProcessor())->process();
        self::assertIsString($content);

        $file = $this->workDir.'/'.$name;
        file_put_contents($file, $content);

        if (str_ends_with($name, '.pdf')) {
            self::assertStringStartsWith('%PDF-', $content);
        }

        return $file;
    }

    private function pageCount(string $file): int
    {
        $qpdf = self::getContainer()->get('daif_chrome_pdf.engine.qpdf');
        self::assertInstanceOf(Qpdf::class, $qpdf);

        return $qpdf->pageCount($file);
    }

    private function exiftool(): Exiftool
    {
        $exiftool = self::getContainer()->get('daif_chrome_pdf.engine.exiftool');
        self::assertInstanceOf(Exiftool::class, $exiftool);

        return $exiftool;
    }

    /**
     * Uncompressed (QDF) form of a PDF, so its dictionaries can be inspected as text.
     */
    private function qdf(string $file): string
    {
        $output = $file.'.qdf';
        (new Process([$this->binary('qpdf'), '--qdf', '--object-streams=disable', $file, $output]))->mustRun();

        return (string) file_get_contents($output);
    }

    /**
     * @param 'qpdf'|'exiftool'|'soffice' $tool
     */
    private function binary(string $tool): string
    {
        return (new BinaryFinder())->find($tool);
    }

    private function useChrome(): void
    {
        $binary = ChromeAvailability::binaryOrSkip();

        foreach (['CHROME_PDF_TEST_BINARY' => $binary, 'CHROME_PDF_TEST_NO_SANDBOX' => ChromeAvailability::noSandbox() ? '1' : '0'] as $name => $value) {
            $_SERVER[$name] = $_ENV[$name] = $value;
            putenv($name.'='.$value);
        }

        // The browser service reads its settings at instantiation: boot again with the new environment.
        self::ensureKernelShutdown();
        self::bootKernel(['test_case' => 'Engine']);
    }
}
