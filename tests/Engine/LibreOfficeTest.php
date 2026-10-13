<?php

namespace Daif\ChromePdfBundle\Tests\Engine;

use Daif\ChromePdfBundle\Engine\BinaryFinder;
use Daif\ChromePdfBundle\Engine\Exiftool;
use Daif\ChromePdfBundle\Engine\LibreOffice;
use Daif\ChromePdfBundle\Engine\Qpdf;
use Daif\ChromePdfBundle\Exception\EngineException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

#[Group('engine')]
final class LibreOfficeTest extends TestCase
{
    private const FIXTURES = __DIR__.'/../Fixtures/engine';

    private string $directory;
    private LibreOffice $libreOffice;
    private Qpdf $qpdf;

    protected function setUp(): void
    {
        EngineAvailability::binaryOrSkip('soffice');
        EngineAvailability::binaryOrSkip('qpdf');
        $this->directory = sys_get_temp_dir().\DIRECTORY_SEPARATOR.'chrome_pdf_libreoffice_test_'.bin2hex(random_bytes(4));
        (new Filesystem())->mkdir($this->directory);
        $this->libreOffice = new LibreOffice(new BinaryFinder(), timeout: 120000, tempDir: $this->directory.'/tmp');
        $this->qpdf = new Qpdf(new BinaryFinder(), tempDir: $this->directory.'/tmp');
    }

    protected function tearDown(): void
    {
        if (isset($this->directory)) {
            (new Filesystem())->remove($this->directory);
        }
    }

    public function testConvert(): void
    {
        $output = $this->libreOffice->convert(self::FIXTURES.'/document.docx', $this->directory.'/out');

        self::assertSame($this->directory.'/out'.\DIRECTORY_SEPARATOR.'document.pdf', $output);
        self::assertStringStartsWith('%PDF-', (string) file_get_contents($output));
        self::assertSame(5, $this->qpdf->pageCount($output));
        self::assertSame([595.0, 842.0], array_map('round', $this->qpdf->pageSize($output)));
        self::assertSame([], glob($this->directory.'/tmp/*') ?: [], 'the profile directory is removed');
    }

    public function testConvertNonAsciiFileName(): void
    {
        $input = $this->directory.'/Résumé été.docx';
        copy(self::FIXTURES.'/document.docx', $input);

        $output = $this->libreOffice->convert($input, $this->directory.'/sortie é');

        self::assertSame($this->directory.'/sortie é'.\DIRECTORY_SEPARATOR.'Résumé été.pdf', $output);
        self::assertSame(5, $this->qpdf->pageCount($output));
    }

    public function testInvalidUtf8PasswordIsRejected(): void
    {
        $this->expectException(EngineException::class);
        $this->expectExceptionMessage('must be valid UTF-8');

        $this->libreOffice->convert(self::FIXTURES.'/protected.odt', $this->directory.'/out', [], "\xff\xfe");
    }

    public function testConvertWithFilterOptionsAndLandscape(): void
    {
        $output = $this->libreOffice->convert(self::FIXTURES.'/document.docx', $this->directory.'/dir é', [
            'PageRange' => '2-3',
            'ExportFormFields' => false,
            'Quality' => 80,
            'Watermark' => "Draft \"1\"\nline",
        ], null, true);

        self::assertSame(2, $this->qpdf->pageCount($output));
        self::assertSame([842.0, 595.0], array_map('round', $this->qpdf->pageSize($output)));
    }

    public function testConvertPdfA(): void
    {
        EngineAvailability::binaryOrSkip('exiftool');

        $output = $this->libreOffice->convert(self::FIXTURES.'/document.docx', $this->directory, ['SelectPdfVersion' => 2]);

        $metadata = (new Exiftool(new BinaryFinder()))->readMetadata($output);
        self::assertSame(2, $metadata['Part']);
        self::assertSame('B', $metadata['Conformance']);
    }

    public function testConvertPasswordProtectedDocument(): void
    {
        $output = $this->libreOffice->convert(self::FIXTURES.'/protected.odt', $this->directory, [], 'secret');

        self::assertSame(5, $this->qpdf->pageCount($output));
    }

    public function testWrongPassword(): void
    {
        $this->expectException(EngineException::class);
        $this->expectExceptionMessage('unable to open the document');

        $this->libreOffice->convert(self::FIXTURES.'/protected.odt', $this->directory, [], 'wrong');
    }

    public function testInvalidFilterOption(): void
    {
        $this->expectException(EngineException::class);
        $this->expectExceptionMessage('The LibreOffice PDF export option "PageRange" must be a string, a boolean or a number, "array" given.');

        $this->libreOffice->convert(self::FIXTURES.'/document.docx', $this->directory, ['PageRange' => [1, 2]]);
    }

    public function testConvertPdfToPdfAAndUa(): void
    {
        EngineAvailability::binaryOrSkip('exiftool');
        $output = $this->directory.'/pdfa.pdf';

        $this->libreOffice->convertPdf(self::FIXTURES.'/five-pages.pdf', $output, 'PDF/A-1b', true);

        self::assertSame(5, $this->qpdf->pageCount($output));
        $metadata = (new Exiftool(new BinaryFinder()))->readMetadata($output);
        self::assertSame(1, $metadata['Part']);
        self::assertSame(1.4, $metadata['PDFVersion']);
    }

    public function testConvertPdfInvalidFormat(): void
    {
        $this->expectException(EngineException::class);
        $this->expectExceptionMessage('Invalid PDF format "PDF/A-4"');

        $this->libreOffice->convertPdf(self::FIXTURES.'/five-pages.pdf', $this->directory.'/x.pdf', 'PDF/A-4', false);
    }
}
