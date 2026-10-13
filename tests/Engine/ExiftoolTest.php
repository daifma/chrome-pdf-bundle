<?php

namespace Daif\ChromePdfBundle\Tests\Engine;

use Daif\ChromePdfBundle\Engine\BinaryFinder;
use Daif\ChromePdfBundle\Engine\Exiftool;
use Daif\ChromePdfBundle\Exception\EngineException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

#[Group('engine')]
final class ExiftoolTest extends TestCase
{
    private string $directory;
    private Exiftool $exiftool;

    protected function setUp(): void
    {
        EngineAvailability::binaryOrSkip('exiftool');
        $this->directory = sys_get_temp_dir().\DIRECTORY_SEPARATOR.'chrome_pdf_exiftool_test_'.bin2hex(random_bytes(4));
        (new Filesystem())->mkdir($this->directory);
        $this->exiftool = new Exiftool(new BinaryFinder(), timeout: 60000, tempDir: $this->directory);
    }

    protected function tearDown(): void
    {
        if (isset($this->directory)) {
            (new Filesystem())->remove($this->directory);
        }
    }

    public function testWriteAndRead(): void
    {
        $file = $this->directory.'/métadonnées.pdf';
        copy(__DIR__.'/../Fixtures/engine/five-pages.pdf', $file);

        $this->exiftool->writeMetadata($file, [
            'Author' => "José \"Q\"\nsecond line \\ backslash",
            'Title' => 'Été',
            'Subject' => 'Subject',
            'Keywords' => ['first', 'second keyword'],
            'Creator' => 'Creator',
            'Producer' => 'Producer',
            'Copyright' => '(c) 2026',
            'CreationDate' => '2024:01:02 10:00:00+01:00',
            'Trapped' => 'True',
            'Marked' => true,
        ]);

        $metadata = $this->exiftool->readMetadata($file);
        self::assertSame("José \"Q\"\nsecond line \\ backslash", $metadata['Author']);
        self::assertSame('Été', $metadata['Title']);
        self::assertSame('Subject', $metadata['Subject']);
        self::assertSame(['first', 'second keyword'], $metadata['Keywords']);
        self::assertSame('Creator', $metadata['Creator']);
        self::assertSame('Producer', $metadata['Producer']);
        self::assertSame('(c) 2026', $metadata['Copyright']);
        self::assertSame('2024:01:02 10:00:00+01:00', $metadata['CreationDate']);
        self::assertTrue($metadata['Trapped']);
        self::assertTrue($metadata['Marked']);
        self::assertSame(5, $metadata['PageCount']);
        self::assertSame(['métadonnées.pdf'], array_values(array_diff(scandir($this->directory) ?: [], ['.', '..'])), 'the argument file directory is removed');
    }

    public function testInvalidKey(): void
    {
        $file = $this->directory.'/file.pdf';
        copy(__DIR__.'/../Fixtures/engine/five-pages.pdf', $file);

        $this->expectException(EngineException::class);
        $this->expectExceptionMessage('Invalid metadata key "-Author"');

        $this->exiftool->writeMetadata($file, ['-Author' => 'x']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function forbiddenKeys(): iterable
    {
        yield 'pseudo tag moving the file' => ['FileName'];
        yield 'pseudo tag in a group' => ['XMP:Directory'];
        yield 'symlink' => ['SymLink'];
        yield 'lowercase pseudo tag' => ['filename'];
        yield 'option importing a file' => ['json'];
        yield 'csv option' => ['csv'];
        yield 'system group' => ['System:FileName'];
        yield 'file group' => ['File:Comment'];
    }

    #[DataProvider('forbiddenKeys')]
    public function testForbiddenKeysAreRejectedBeforeRunningExiftool(string $key): void
    {
        $file = $this->directory.'/file.pdf';
        copy(__DIR__.'/../Fixtures/engine/five-pages.pdf', $file);

        try {
            $this->exiftool->writeMetadata($file, [$key => $this->directory.'/elsewhere.pdf']);
            self::fail('Expected an EngineException.');
        } catch (EngineException $e) {
            self::assertStringContainsString('Invalid metadata key', $e->getMessage());
        }
        self::assertFileExists($file);
        self::assertFileDoesNotExist($this->directory.'/elsewhere.pdf');
    }

    public function testGroupedXmpKeyIsAccepted(): void
    {
        $file = $this->directory.'/file.pdf';
        copy(__DIR__.'/../Fixtures/engine/five-pages.pdf', $file);

        $this->exiftool->writeMetadata($file, ['XMP-dc:Rights' => 'All rights reserved']);

        self::assertSame('All rights reserved', $this->exiftool->readMetadata($file)['Rights'] ?? null);
    }

    public function testInvalidValue(): void
    {
        $file = $this->directory.'/file.pdf';
        copy(__DIR__.'/../Fixtures/engine/five-pages.pdf', $file);

        $this->expectException(EngineException::class);
        $this->expectExceptionMessage('The metadata "Author" must be a string');

        /* @phpstan-ignore argument.type */
        $this->exiftool->writeMetadata($file, ['Author' => new \stdClass()]);
    }

    public function testMissingFile(): void
    {
        $this->expectException(EngineException::class);

        $this->exiftool->readMetadata($this->directory.'/missing.pdf');
    }

    public function testUnreadableFileIsReported(): void
    {
        $file = $this->directory.'/broken.pdf';
        file_put_contents($file, 'not a pdf');

        $this->expectException(EngineException::class);
        $this->expectExceptionMessageMatches('/^exiftool failed with exit code 1: /');

        $this->exiftool->writeMetadata($file, ['Author' => 'x']);
    }
}
