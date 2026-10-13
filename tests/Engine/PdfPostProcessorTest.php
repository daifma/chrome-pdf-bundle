<?php

namespace Daif\ChromePdfBundle\Tests\Engine;

use Daif\ChromePdfBundle\Browser\BrowserInterface;
use Daif\ChromePdfBundle\Browser\ChromeBrowser;
use Daif\ChromePdfBundle\Engine\BinaryFinder;
use Daif\ChromePdfBundle\Engine\Exiftool;
use Daif\ChromePdfBundle\Engine\LibreOffice;
use Daif\ChromePdfBundle\Engine\PdfPostProcessor;
use Daif\ChromePdfBundle\Engine\Qpdf;
use Daif\ChromePdfBundle\Exception\EngineException;
use Daif\ChromePdfBundle\Tests\Functional\ChromeAvailability;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

#[Group('engine')]
final class PdfPostProcessorTest extends TestCase
{
    private static ChromeBrowser|null $chrome = null;

    private string $directory;
    private string $input;
    private PdfInspector $inspector;
    private Qpdf $qpdf;
    private Exiftool $exiftool;

    public static function tearDownAfterClass(): void
    {
        self::$chrome?->close();
        self::$chrome = null;
    }

    protected function setUp(): void
    {
        $this->inspector = new PdfInspector(EngineAvailability::binaryOrSkip('qpdf'));
        EngineAvailability::binaryOrSkip('exiftool');
        $this->directory = sys_get_temp_dir().\DIRECTORY_SEPARATOR.'chrome_pdf_post_processor_test_'.bin2hex(random_bytes(4));
        (new Filesystem())->mkdir($this->directory.'/work');
        $this->input = $this->directory.'/input.pdf';
        copy(__DIR__.'/../Fixtures/engine/five-pages.pdf', $this->input);

        $finder = new BinaryFinder();
        $this->qpdf = new Qpdf($finder, tempDir: $this->directory);
        $this->exiftool = new Exiftool($finder, tempDir: $this->directory);
    }

    protected function tearDown(): void
    {
        if (isset($this->directory)) {
            (new Filesystem())->remove($this->directory);
        }
    }

    public function testNoOptionReturnsTheInput(): void
    {
        self::assertSame([$this->input], $this->processor()->process($this->input, [], $this->directory.'/work'));
    }

    public function testSplitWithoutUnifyProducesSeveralFilesAndProcessesEachOne(): void
    {
        $files = $this->processor()->process($this->input, [
            'split' => ['mode' => 'pages', 'span' => '1-2,5', 'unify' => false],
            'metadata' => ['Title' => 'Split'],
            'rotate' => ['angle' => 90, 'pages' => null],
        ], $this->directory.'/work');

        self::assertCount(2, $files);
        self::assertSame(['input_1.pdf', 'input_2.pdf'], array_map('basename', $files));
        self::assertSame([2, 1], array_map($this->qpdf->pageCount(...), $files));
        foreach ($files as $file) {
            self::assertSame('Split', $this->exiftool->readMetadata($file)['Title']);
            self::assertSame([842.0, 595.0], array_map('round', $this->qpdf->pageSize($file)));
        }
    }

    public function testSplitWithEnums(): void
    {
        $files = $this->processor()->process($this->input, [
            'split' => ['mode' => TestSplitMode::Intervals, 'span' => '3', 'unify' => false],
        ], $this->directory.'/work');

        self::assertSame([3, 2], array_map($this->qpdf->pageCount(...), $files));
    }

    public function testFullPipeline(): void
    {
        $hash = md5_file($this->input);
        $attachment = $this->directory.'/data.xml';
        file_put_contents($attachment, '<data/>');

        $files = $this->processor($this->chrome())->process($this->input, [
            'metadata' => ['Author' => 'Engine', 'Subject' => 'Pipeline'],
            'flatten' => true,
            'rotate' => ['angle' => 180, 'pages' => '5'],
            'watermark' => ['source' => 'text', 'expression' => 'CONFIDENTIAL', 'pages' => null, 'options' => ['color' => 'red', 'points' => 60]],
            'stamp' => ['source' => 'image', 'expression' => __DIR__.'/../Fixtures/assets/logo.png', 'pages' => '1-2', 'options' => ['scale' => 0.2]],
            'bookmarks' => [['title' => 'Start', 'page' => 1], ['title' => 'End', 'page' => 5]],
            'embeds' => [$attachment],
            'encrypt' => ['userPassword' => 'user', 'ownerPassword' => 'owner'],
        ], $this->directory.'/work');

        self::assertCount(1, $files);
        self::assertSame($hash, md5_file($this->input), 'the input file is not modified');
        $file = $files[0];
        self::assertSame('input.pdf', basename($file));

        self::assertSame(0, $this->inspector->run(['--requires-password', $file])->getExitCode());
        $decrypted = $this->directory.'/decrypted.pdf';
        $process = $this->inspector->run(['--password=owner', '--decrypt', $file, $decrypted]);
        self::assertContains($process->getExitCode(), [0, 3], $process->getErrorOutput());

        $metadata = $this->exiftool->readMetadata($decrypted);
        self::assertSame('Engine', $metadata['Author']);
        self::assertSame('Pipeline', $metadata['Subject']);
        self::assertSame(5, $metadata['PageCount']);

        $json = $this->inspector->json(['--json=2', '--json-key=outlines', '--json-key=attachments', $decrypted]);
        self::assertSame(['Start', 'End'], array_column($json['outlines'], 'title'));
        self::assertArrayHasKey('data.xml', $json['attachments']);
        self::assertSame([0, 0, 0, 0, 180], $this->inspector->rotations($decrypted));

        // the watermark adds a form on every page, the image stamp on pages 1 and 2 only
        $original = $this->inspector->reachableXObjects($this->input);
        foreach ($this->inspector->reachableXObjects($decrypted) as $page => $counts) {
            self::assertGreaterThan($original[$page]['forms'], $counts['forms'], 'page '.($page + 1));
            self::assertSame($page < 2 ? 1 : 0, $counts['images'], 'page '.($page + 1));
        }
    }

    public function testStampFromPdf(): void
    {
        $files = $this->processor()->process($this->input, [
            'stamp' => ['source' => 'pdf', 'expression' => $this->input, 'pages' => '3', 'options' => []],
        ], $this->directory.'/work');

        self::assertSame(5, $this->qpdf->pageCount($files[0]));
    }

    public function testTextStampRendering(): void
    {
        $browser = new class implements BrowserInterface {
            /** @var list<array{string, array<string, mixed>, array<string, mixed>}> */
            public array $calls = [];

            public function htmlToPdf(string $html, array $pdfOptions = [], array $pageOptions = []): string
            {
                $this->calls[] = [$html, $pdfOptions, $pageOptions];

                return (string) file_get_contents(__DIR__.'/../Fixtures/engine/five-pages.pdf');
            }

            public function urlToPdf(string $url, array $pdfOptions = [], array $pageOptions = []): string
            {
                throw new \LogicException('Not expected.');
            }

            public function htmlToScreenshot(string $html, array $screenshotOptions = [], array $pageOptions = []): string
            {
                throw new \LogicException('Not expected.');
            }

            public function urlToScreenshot(string $url, array $screenshotOptions = [], array $pageOptions = []): string
            {
                throw new \LogicException('Not expected.');
            }
        };

        $this->processor($browser)->process($this->input, [
            'watermark' => ['source' => 'text', 'expression' => '<b>Draft</b>', 'pages' => '', 'options' => ['font' => 'Courier; }', 'opacity' => 0.25, 'rotation' => 30, 'ignored' => 'x']],
        ], $this->directory.'/work');

        self::assertCount(1, $browser->calls);
        [$html, $pdfOptions, $pageOptions] = $browser->calls[0];
        self::assertStringContainsString('&lt;b&gt;Draft&lt;/b&gt;', $html);
        self::assertStringContainsString('font-family: Courier', $html);
        self::assertStringNotContainsString('Courier; }', $html);
        self::assertStringContainsString('opacity: 0.25; transform: rotate(-30deg);', $html);
        self::assertStringContainsString('font-size: 48pt', $html);
        self::assertStringContainsString('color: #808080', $html);
        self::assertSame(595.0, round($pdfOptions['paperWidth'] * 72));
        self::assertSame(842.0, round($pdfOptions['paperHeight'] * 72));
        self::assertSame(0, $pdfOptions['marginTop']);
        self::assertTrue($pageOptions['omitBackground']);
    }

    public function testPdfFormat(): void
    {
        EngineAvailability::binaryOrSkip('soffice');

        $files = $this->processor()->process($this->input, ['pdfFormat' => 'PDF/A-2b', 'pdfUniversalAccess' => false, 'metadata' => ['Title' => 'Archive']], $this->directory.'/work');

        $metadata = $this->exiftool->readMetadata($files[0]);
        self::assertSame(2, $metadata['Part']);
        self::assertSame('Archive', $metadata['Title']);
    }

    public function testInvalidStampSource(): void
    {
        $this->expectException(EngineException::class);
        $this->expectExceptionMessage('Invalid stamp source "video"');

        $this->processor()->process($this->input, ['stamp' => ['source' => 'video', 'expression' => 'x']], $this->directory.'/work');
    }

    public function testInvalidOptionType(): void
    {
        $this->expectException(EngineException::class);
        $this->expectExceptionMessage('The "flatten" option must be a boolean');

        $this->processor()->process($this->input, ['flatten' => 'yes'], $this->directory.'/work');
    }

    private function processor(BrowserInterface|null $browser = null): PdfPostProcessor
    {
        $finder = new BinaryFinder();

        return new PdfPostProcessor(
            $this->qpdf,
            $this->exiftool,
            new LibreOffice($finder, tempDir: $this->directory),
            $browser ?? $this->createMock(BrowserInterface::class),
            $this->directory,
        );
    }

    private function chrome(): ChromeBrowser
    {
        $binary = ChromeAvailability::binaryOrSkip();

        return self::$chrome ??= new ChromeBrowser($binary, noSandbox: ChromeAvailability::noSandbox());
    }
}

enum TestSplitMode: string
{
    case Intervals = 'intervals';
    case Pages = 'pages';
}
