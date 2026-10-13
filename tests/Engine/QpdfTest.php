<?php

namespace Daif\ChromePdfBundle\Tests\Engine;

use Daif\ChromePdfBundle\Engine\BinaryFinder;
use Daif\ChromePdfBundle\Engine\Qpdf;
use Daif\ChromePdfBundle\Exception\EngineException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

#[Group('engine')]
final class QpdfTest extends TestCase
{
    private PdfInspector $inspector;
    private string $directory;
    /** fixtures copied under the temporary directory, for the qpdf AppArmor profile of recent Ubuntu releases */
    private string $fixtures;
    private Qpdf $qpdf;

    protected function setUp(): void
    {
        $this->inspector = new PdfInspector(EngineAvailability::binaryOrSkip('qpdf'));
        $this->directory = sys_get_temp_dir().\DIRECTORY_SEPARATOR.'chrome_pdf_qpdf_test_'.bin2hex(random_bytes(4));
        $this->fixtures = $this->directory.\DIRECTORY_SEPARATOR.'fixtures';
        (new Filesystem())->mirror(__DIR__.'/../Fixtures/engine', $this->fixtures);
        $this->qpdf = new Qpdf(new BinaryFinder(), timeout: 60000, tempDir: $this->directory);
    }

    protected function tearDown(): void
    {
        if (isset($this->directory)) {
            (new Filesystem())->remove($this->directory);
        }
    }

    public function testPageCount(): void
    {
        self::assertSame(5, $this->qpdf->pageCount($this->fixtures.'/five-pages.pdf'));
    }

    public function testMerge(): void
    {
        $output = $this->directory.'/merged.pdf';
        $this->qpdf->merge([$this->fixtures.'/five-pages.pdf', $this->fixtures.'/form.pdf'], $output);

        self::assertSame(6, $this->qpdf->pageCount($output));
        $this->assertValid($output);
        self::assertSame(['fixtures', 'merged.pdf'], $this->listDirectory(), 'the temporary directories are removed');
    }

    public function testMergeRequiresAFile(): void
    {
        $this->expectException(EngineException::class);

        $this->qpdf->merge([], $this->directory.'/merged.pdf');
    }

    public function testSplitIntervals(): void
    {
        $files = $this->qpdf->split($this->fixtures.'/five-pages.pdf', 'intervals', '2', false, $this->directory.'/out');

        self::assertSame([$this->directory.'/out/five-pages_1.pdf', $this->directory.'/out/five-pages_2.pdf', $this->directory.'/out/five-pages_3.pdf'], $files);
        self::assertSame([2, 2, 1], array_map($this->qpdf->pageCount(...), $files));
    }

    public function testSplitPages(): void
    {
        $files = $this->qpdf->split($this->fixtures.'/five-pages.pdf', 'pages', '1-2,4', false, $this->directory);

        self::assertCount(2, $files);
        self::assertSame([2, 1], array_map($this->qpdf->pageCount(...), $files));
    }

    public function testSplitPagesUnify(): void
    {
        $files = $this->qpdf->split($this->fixtures.'/five-pages.pdf', 'pages', '1-2,4', true, $this->directory);

        self::assertCount(1, $files);
        self::assertSame(3, $this->qpdf->pageCount($files[0]));
    }

    public function testSplitInvalidSpan(): void
    {
        $this->expectException(EngineException::class);
        $this->expectExceptionMessage('Invalid split span "1-2" for the "intervals" mode');

        $this->qpdf->split($this->fixtures.'/five-pages.pdf', 'intervals', '1-2', false, $this->directory);
    }

    public function testSplitSelectingNoPage(): void
    {
        $this->expectException(EngineException::class);
        $this->expectExceptionMessage('selects no page');

        $this->qpdf->split($this->fixtures.'/five-pages.pdf', 'pages', '9-10', false, $this->directory);
    }

    public function testRotateSelectedPages(): void
    {
        $output = $this->directory.'/rotated.pdf';
        $this->qpdf->rotate($this->fixtures.'/five-pages.pdf', $output, 90, '2-3');

        self::assertSame([0, 90, 90, 0, 0], $this->inspector->rotations($output));
        self::assertSame([842.0, 595.0], array_map('round', $this->qpdf->pageSize($output, 2)));
        self::assertSame([595.0, 842.0], array_map('round', $this->qpdf->pageSize($output, 1)));
    }

    public function testRotateInPlace(): void
    {
        $file = $this->directory.'/in-place.pdf';
        copy($this->fixtures.'/five-pages.pdf', $file);
        $this->qpdf->rotate($file, $file, 180);

        self::assertSame([180, 180, 180, 180, 180], $this->inspector->rotations($file));
    }

    public function testRotateInvalidAngle(): void
    {
        $this->expectException(EngineException::class);

        $this->qpdf->rotate($this->fixtures.'/five-pages.pdf', $this->directory.'/x.pdf', 45);
    }

    public function testFlatten(): void
    {
        $output = $this->directory.'/flat.pdf';
        self::assertSame(1, $this->annotationCount($this->fixtures.'/form.pdf'));

        $this->qpdf->flatten($this->fixtures.'/form.pdf', $output);

        self::assertSame(0, $this->annotationCount($output));
        $this->assertValid($output);
    }

    public function testEncrypt(): void
    {
        $output = $this->directory.'/encrypted.pdf';
        $this->qpdf->encrypt($this->fixtures.'/five-pages.pdf', $output, 'user "pw"', 'owner pw');

        self::assertSame(0, $this->inspector->run(['--is-encrypted', $output])->getExitCode());
        self::assertSame(0, $this->inspector->run(['--requires-password', $output])->getExitCode());
        $encryption = $this->inspector->run(['--password=owner pw', '--show-encryption', $output])->getOutput();
        self::assertStringContainsString('Supplied password is owner password', $encryption);
        self::assertStringContainsString('R = 6', $encryption, 'AES-256');
        self::assertStringContainsString('Supplied password is user password', $this->inspector->run(['--password=user "pw"', '--show-encryption', $output])->getOutput());
        self::assertSame(2, $this->inspector->run(['--password=wrong', '--check', $output])->getExitCode());
    }

    public function testEncryptOwnerDefaultsToUser(): void
    {
        $output = $this->directory.'/encrypted.pdf';
        $this->qpdf->encrypt($this->fixtures.'/five-pages.pdf', $output, 'secret', null);

        $encryption = $this->inspector->run(['--password=secret', '--show-encryption', $output])->getOutput();
        self::assertStringContainsString('Supplied password is owner password', $encryption);
        self::assertStringContainsString('User password = secret', $encryption);
    }

    public function testEncryptPasswordStartingWithADash(): void
    {
        $output = $this->directory.'/encrypted.pdf';
        $version = trim((string) preg_replace('/^qpdf version (\S+).*/s', '$1', $this->inspector->run(['--version'])->getOutput()));

        if (version_compare($version, '11.7', '<')) {
            try {
                $this->qpdf->encrypt($this->fixtures.'/five-pages.pdf', $output, '-secret', null);
                self::fail('Expected an EngineException.');
            } catch (EngineException $e) {
                self::assertStringContainsString('require qpdf 11.7', $e->getMessage());
                self::assertStringNotContainsString('-secret', $e->getMessage());
            }

            return;
        }

        $this->qpdf->encrypt($this->fixtures.'/five-pages.pdf', $output, '-secret', '--');
        self::assertStringContainsString('Supplied password is user password', $this->inspector->run(['--password=-secret', '--show-encryption', $output])->getOutput());
    }

    public function testEmbedRejectsDuplicateNames(): void
    {
        (new Filesystem())->mkdir([$this->directory.'/a', $this->directory.'/b']);
        file_put_contents($this->directory.'/a/data.xml', '<a/>');
        file_put_contents($this->directory.'/b/data.xml', '<b/>');

        $this->expectException(EngineException::class);
        $this->expectExceptionMessage('Two embedded files are named "data.xml"');

        $this->qpdf->embed($this->fixtures.'/five-pages.pdf', $this->directory.'/x.pdf', [$this->directory.'/a/data.xml', $this->directory.'/b/data.xml']);
    }

    public function testEncryptRequiresAPassword(): void
    {
        $this->expectException(EngineException::class);

        $this->qpdf->encrypt($this->fixtures.'/five-pages.pdf', $this->directory.'/x.pdf', null, '');
    }

    public function testEmbed(): void
    {
        $xml = $this->directory.'/factur-x.xml';
        file_put_contents($xml, '<invoice/>');
        $output = $this->directory.'/embedded.pdf';

        $this->qpdf->embed($this->fixtures.'/five-pages.pdf', $output, [$xml, $this->fixtures.'/form.pdf']);

        $attachments = $this->inspector->run(['--list-attachments', '--verbose', $output])->getOutput();
        self::assertStringContainsString('factur-x.xml -> ', $attachments);
        self::assertStringContainsString('mime type: text/xml', $attachments);
        self::assertStringContainsString('form.pdf -> ', $attachments);
        self::assertSame('<invoice/>', $this->inspector->run(['--show-attachment=factur-x.xml', $output])->getOutput());
    }

    public function testOverlayOnSelectedPages(): void
    {
        $stamp = $this->directory.'/stamp.pdf';
        $this->qpdf->split($this->fixtures.'/five-pages.pdf', 'pages', '5', false, $this->directory);
        rename($this->directory.'/five-pages_1.pdf', $stamp);
        $output = $this->directory.'/stamped.pdf';

        $this->qpdf->overlay($this->fixtures.'/five-pages.pdf', $output, $stamp, false, '2-3');

        $original = $this->inspector->drawnXObjects($this->fixtures.'/five-pages.pdf');
        $stamped = $this->inspector->drawnXObjects($output);
        self::assertSame($original[0], $stamped[0]);
        self::assertGreaterThan($original[1], $stamped[1]);
        self::assertGreaterThan($original[2], $stamped[2]);
        self::assertSame($original[3], $stamped[3]);
        $this->assertValid($output);
    }

    public function testUnderlayOnEveryPage(): void
    {
        $output = $this->directory.'/underlay.pdf';
        $this->qpdf->overlay($this->fixtures.'/five-pages.pdf', $output, $this->fixtures.'/five-pages.pdf', true);

        $original = $this->inspector->drawnXObjects($this->fixtures.'/five-pages.pdf');
        foreach ($this->inspector->drawnXObjects($output) as $page => $count) {
            self::assertGreaterThan($original[$page], $count);
        }
    }

    public function testAddBookmarks(): void
    {
        $output = $this->directory.'/bookmarks.pdf';
        $this->qpdf->addBookmarks($this->fixtures.'/five-pages.pdf', $output, [
            ['title' => 'Introduction é', 'page' => 1, 'children' => [['title' => 'Overview', 'page' => 2]]],
            ['title' => 'Appendix', 'page' => 5],
        ]);

        $outlines = $this->inspector->json(['--json=2', '--json-key=outlines', $output])['outlines'];
        self::assertSame('Introduction é', $outlines[0]['title']);
        self::assertSame(1, $outlines[0]['destpageposfrom1']);
        self::assertSame('Overview', $outlines[0]['kids'][0]['title']);
        self::assertSame(2, $outlines[0]['kids'][0]['destpageposfrom1']);
        self::assertSame('Appendix', $outlines[1]['title']);
        self::assertSame(5, $outlines[1]['destpageposfrom1']);
        $this->assertValid($output);

        // replacing the outline
        $this->qpdf->addBookmarks($output, $output, [['title' => 'Only', 'page' => 3]]);
        $outlines = $this->inspector->json(['--json=2', '--json-key=outlines', $output])['outlines'];
        self::assertCount(1, $outlines);
        self::assertSame(3, $outlines[0]['destpageposfrom1']);
    }

    public function testBookmarkOutOfRange(): void
    {
        $this->expectException(EngineException::class);
        $this->expectExceptionMessage('The bookmark "Far" targets page 9, the document has 5 page(s).');

        $this->qpdf->addBookmarks($this->fixtures.'/five-pages.pdf', $this->directory.'/x.pdf', [['title' => 'Far', 'page' => 9]]);
    }

    public function testPageSize(): void
    {
        self::assertSame([595.0, 842.0], array_map('round', $this->qpdf->pageSize($this->fixtures.'/five-pages.pdf')));
    }

    public function testInputWithoutPdfExtension(): void
    {
        $file = $this->directory.'/upload';
        copy($this->fixtures.'/five-pages.pdf', $file);

        self::assertSame(5, $this->qpdf->pageCount($file));
    }

    public function testMissingInput(): void
    {
        $this->expectException(EngineException::class);
        $this->expectExceptionMessage('does not exist or is not readable');

        $this->qpdf->pageCount($this->directory.'/missing.pdf');
    }

    public function testQpdfFailureIsReported(): void
    {
        $file = $this->directory.'/broken.pdf';
        file_put_contents($file, 'not a pdf');

        $this->expectException(EngineException::class);
        $this->expectExceptionMessageMatches('/^qpdf failed with exit code 2: .+/');

        $this->qpdf->flatten($file, $this->directory.'/x.pdf');
    }

    public function testConfiguredBinaryNotFound(): void
    {
        $qpdf = new Qpdf(new BinaryFinder(), '/nowhere/qpdf');

        $this->expectException(EngineException::class);
        $this->expectExceptionMessage('daif_chrome_pdf.qpdf_binary');

        $qpdf->pageCount($this->fixtures.'/five-pages.pdf');
    }

    private function assertValid(string $file): void
    {
        $process = $this->inspector->run(['--check', $file]);
        self::assertSame(0, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());
    }

    private function annotationCount(string $file): int
    {
        $count = 0;
        foreach ($this->inspector->json(['--json=2', '--json-key=qpdf', $file])['qpdf'][1] as $object) {
            if (isset($object['value']['/Type']) && '/Annot' === $object['value']['/Type']) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * @return list<string>
     */
    private function listDirectory(): array
    {
        return array_values(array_diff(scandir($this->directory) ?: [], ['.', '..']));
    }
}
