<?php

namespace Daif\ChromePdfBundle\Engine;

use Daif\ChromePdfBundle\Exception\EngineException;
use Psr\Log\LoggerInterface;

/**
 * Reads and writes PDF metadata with ExifTool (https://exiftool.org).
 *
 * Arguments go through a UTF-8 argument file: it keeps non-ASCII values and file names intact
 * on Windows and allows line breaks in values.
 */
final class Exiftool
{
    // writable pseudo tags that move, link or touch files instead of writing metadata
    private const FORBIDDEN_TAGS = ['filename', 'directory', 'symlink', 'hardlink', 'testname', 'filemodifydate', 'fileaccessdate', 'filecreatedate', 'fileinodechangedate', 'filepermissions', 'fileuserid', 'filegroupid', 'fileattributes', 'zoneidentifier', 'geotag', 'geosync', 'geotime', 'forcewrite'];

    private readonly ProcessRunner $runner;
    private string|null $resolvedBinary = null;

    public function __construct(
        private readonly BinaryFinder $binaryFinder,
        private readonly string|null $binary = null,
        private readonly LoggerInterface|null $logger = null,
        int $timeout = 120000,
        private readonly string|null $tempDir = null,
    ) {
        $this->runner = new ProcessRunner('exiftool', $logger, $timeout);
    }

    /**
     * Writes the metadata in place. Keys are ExifTool tag names, optionally with a group
     * ("Author", "Title", "Keywords", "XMP-fx:DocumentType"...). A list writes a list tag,
     * an empty string or an empty list deletes the tag.
     *
     * @param array<string, string|bool|int|float|list<string>|null> $metadata
     */
    public function writeMetadata(string $file, array $metadata): void
    {
        self::assertFile($file);
        if ([] === $metadata) {
            return;
        }

        $arguments = ['-charset', 'utf8', '-charset', 'filename=utf8', '-overwrite_original', '-m'];
        foreach ($metadata as $key => $value) {
            $key = (string) $key;
            // tag names start with an uppercase letter, which also rules out exiftool options such as "json=FILE"
            if (1 !== preg_match('/^(?:(?:PDF|XMP(?:-[A-Za-z0-9]+)?):)?([A-Z][A-Za-z0-9_]*)$/', $key, $matches)
                || \in_array(strtolower($matches[1]), self::FORBIDDEN_TAGS, true)
                || str_starts_with(strtolower($matches[1]), 'mditem')
                || str_starts_with(strtolower($matches[1]), 'xattr')) {
                throw new EngineException(\sprintf('Invalid metadata key "%s": expected a PDF or XMP tag name such as "Title" or "XMP-dc:Rights".', $key));
            }

            if (\is_array($value)) {
                if ([] === $value) {
                    $arguments[] = '-'.$key.'=';
                }
                foreach ($value as $item) {
                    $arguments[] = '-'.$key.'='.self::stringify($key, $item);
                }
                continue;
            }

            $arguments[] = '-'.$key.'='.self::stringify($key, $value);
        }
        $arguments[] = self::safePath($file);

        $output = $this->run($arguments);
        if (1 === preg_match('/\b0 image files updated\b/', $output) && 1 !== preg_match('/\b1 image files unchanged\b/', $output)) {
            throw new EngineException(\sprintf('exiftool did not update "%s": %s', $file, trim($output)));
        }
    }

    /**
     * @return array<string, mixed> every tag ExifTool reads, with numeric values (-n)
     */
    public function readMetadata(string $file): array
    {
        self::assertFile($file);

        $output = $this->run(['-charset', 'filename=utf8', '-json', '-n', self::safePath($file)]);
        try {
            $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new EngineException('Unable to decode the exiftool JSON output: '.$e->getMessage(), 0, $e);
        }

        if (!\is_array($data) || !\is_array($data[0] ?? null)) {
            throw new EngineException('Unexpected exiftool JSON output.');
        }

        return $data[0];
    }

    /**
     * @param list<string> $arguments
     */
    private function run(array $arguments): string
    {
        $directory = ProcessRunner::createWorkingDirectory($this->tempDir);
        try {
            // "#[CSTR]" lines are decoded as C strings: line breaks and backslashes survive
            $lines = array_map(static fn (string $argument): string => '#[CSTR]'.addcslashes($argument, "\\\"\0..\37"), $arguments);
            $argumentFile = $directory->writeFile('arguments.args', implode("\n", $lines)."\n");

            return $this->runner->run([...$this->command(), '-@', $argumentFile], [0], null, [...$this->command(), ...$arguments]);
        } finally {
            $directory->remove($this->logger);
        }
    }

    /**
     * @return list<string>
     */
    private function command(): array
    {
        $binary = $this->resolvedBinary ??= $this->binaryFinder->find('exiftool', $this->binary);

        // the Perl distribution of exiftool on Windows is a script without extension
        if ('Windows' === \PHP_OS_FAMILY && !\in_array(strtolower(pathinfo($binary, \PATHINFO_EXTENSION)), ['exe', 'bat', 'cmd', 'com'], true)) {
            return ['perl', $binary];
        }

        return [$binary];
    }

    private static function stringify(string $key, mixed $value): string
    {
        return match (true) {
            \is_bool($value) => $value ? 'true' : 'false',
            \is_string($value) => $value,
            \is_int($value), \is_float($value) => (string) $value,
            null === $value => '',
            default => throw new EngineException(\sprintf('The metadata "%s" must be a string, a boolean, a number or a list of strings, "%s" given.', $key, get_debug_type($value))),
        };
    }

    private static function safePath(string $file): string
    {
        return str_starts_with($file, '-') ? '.'.\DIRECTORY_SEPARATOR.$file : $file;
    }

    private static function assertFile(string $file): void
    {
        if (!is_file($file) || !is_readable($file)) {
            throw new EngineException(\sprintf('The file "%s" does not exist or is not readable.', $file));
        }
    }
}
