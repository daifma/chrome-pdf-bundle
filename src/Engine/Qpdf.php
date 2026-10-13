<?php

namespace Daif\ChromePdfBundle\Engine;

use Daif\ChromePdfBundle\Browser\WorkingDirectory;
use Daif\ChromePdfBundle\Exception\EngineException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * PDF manipulations with the qpdf command line tool (https://qpdf.readthedocs.io).
 *
 * Every command works in a private temporary directory (under the configured temp dir): inputs are
 * linked or copied there under a .pdf name and the result is moved to the requested output afterwards,
 * so the output may be the input file. The qpdf AppArmor profile shipped by recent Ubuntu releases
 * only lets qpdf open *.pdf and *.json files in the home and temporary directories.
 */
final class Qpdf
{
    /** qpdf exits with 3 when it succeeded with warnings */
    private const SUCCESS_CODES = [0, 3];

    private const ROTATE_ANGLES = [90, 180, 270];

    private const SPLIT_MODES = ['intervals', 'pages'];

    private const MIME_TYPES = [
        'csv' => 'text/csv',
        'gif' => 'image/gif',
        'htm' => 'text/html',
        'html' => 'text/html',
        'jpeg' => 'image/jpeg',
        'jpg' => 'image/jpeg',
        'json' => 'application/json',
        'pdf' => 'application/pdf',
        'png' => 'image/png',
        'svg' => 'image/svg+xml',
        'txt' => 'text/plain',
        'xml' => 'text/xml',
        'zip' => 'application/zip',
    ];

    private readonly ProcessRunner $runner;
    private string|null $version = null;
    private readonly Filesystem $filesystem;
    private string|null $resolvedBinary = null;

    public function __construct(
        private readonly BinaryFinder $binaryFinder,
        private readonly string|null $binary = null,
        private readonly LoggerInterface|null $logger = null,
        int $timeout = 120000,
        private readonly string|null $tempDir = null,
    ) {
        $this->runner = new ProcessRunner('qpdf', $logger, $timeout);
        $this->filesystem = new Filesystem();
    }

    /**
     * @param list<string> $inputs PDF files, the pages are concatenated in this order
     */
    public function merge(array $inputs, string $output): void
    {
        if ([] === $inputs) {
            throw new EngineException('At least one PDF file is required to merge.');
        }

        $this->withDirectory(function (WorkingDirectory $directory) use ($inputs, $output): void {
            $command = [$this->binary(), '--empty', '--pages'];
            $index = 0;
            foreach ($inputs as $input) {
                $command[] = $this->stage($directory, $input, 'input_'.$index++);
            }
            $result = $this->outputPath($directory);
            $this->runner->run([...$command, '--', $result], self::SUCCESS_CODES);
            $this->move($result, $output);
        });
    }

    /**
     * @param string $mode  'intervals' (a new file every $span pages) or 'pages' (one file per comma separated page range of $span)
     * @param bool   $unify with the "pages" mode, put every selected page into a single file
     *
     * @return list<string> the produced files, in page order
     */
    public function split(string $input, string $mode, string $span, bool $unify, string $outputDir): array
    {
        if (!\in_array($mode, self::SPLIT_MODES, true)) {
            throw new EngineException(\sprintf('Invalid split mode "%s", expected "intervals" or "pages".', $mode));
        }

        return $this->withDirectory(function (WorkingDirectory $directory) use ($input, $mode, $span, $unify, $outputDir): array {
            $source = $this->stage($directory, $input, 'input');
            $pageCount = $this->countPages($source);

            if ('intervals' === $mode) {
                $span = trim($span);
                if (1 !== preg_match('/^\d+$/', $span) || (int) $span < 1) {
                    throw new EngineException(\sprintf('Invalid split span "%s" for the "intervals" mode, expected a positive number of pages.', $span));
                }
                $groups = array_chunk(range(1, $pageCount), (int) $span);
            } else {
                $groups = PageRanges::parse($span, $pageCount);
                if ([] === $groups) {
                    throw new EngineException(\sprintf('The split span "%s" selects no page, the document has %d page(s).', $span, $pageCount));
                }
                if ($unify) {
                    $groups = [PageRanges::pages($span, $pageCount)];
                }
            }

            $baseName = self::baseName($input);
            $files = [];
            foreach ($groups as $index => $pages) {
                $result = $directory->getPath().\DIRECTORY_SEPARATOR.'split_'.$index.'.pdf';
                $this->runner->run([$this->binary(), $source, '--pages', '.', PageRanges::toQpdf($pages), '--', $result], self::SUCCESS_CODES);
                $target = rtrim($outputDir, '\\/').\DIRECTORY_SEPARATOR.$baseName.'_'.($index + 1).'.pdf';
                $this->move($result, $target);
                $files[] = $target;
            }

            return $files;
        });
    }

    /**
     * @param int         $angle 90, 180 or 270 (clockwise)
     * @param string|null $pages page ranges ("1-3,5"), null for all pages
     */
    public function rotate(string $input, string $output, int $angle, string|null $pages = null): void
    {
        if (!\in_array($angle, self::ROTATE_ANGLES, true)) {
            throw new EngineException(\sprintf('Invalid rotation angle %d, expected 90, 180 or 270.', $angle));
        }

        $this->withDirectory(function (WorkingDirectory $directory) use ($input, $output, $angle, $pages): void {
            $source = $this->stage($directory, $input, 'input');
            $rotate = '--rotate=+'.$angle;
            if (null !== $pages && '' !== trim($pages)) {
                $rotate .= ':'.$this->selectPages($source, $pages);
            }
            $result = $this->outputPath($directory);
            $this->runner->run([$this->binary(), $source, $result, $rotate], self::SUCCESS_CODES);
            $this->move($result, $output);
        });
    }

    /**
     * Flattens form fields and annotations into the page content.
     */
    public function flatten(string $input, string $output): void
    {
        $this->withDirectory(function (WorkingDirectory $directory) use ($input, $output): void {
            $source = $this->stage($directory, $input, 'input');
            $result = $this->outputPath($directory);
            $this->runner->run([$this->binary(), $source, $result, '--generate-appearances', '--flatten-annotations=all'], self::SUCCESS_CODES);
            $this->move($result, $output);
        });
    }

    /**
     * AES-256 encryption. The owner password defaults to the user password.
     */
    public function encrypt(string $input, string $output, string|null $userPassword, string|null $ownerPassword): void
    {
        if ((null === $userPassword || '' === $userPassword) && (null === $ownerPassword || '' === $ownerPassword)) {
            throw new EngineException('A user password or an owner password is required to encrypt a PDF.');
        }
        $userPassword ??= '';
        $ownerPassword = null === $ownerPassword || '' === $ownerPassword ? $userPassword : $ownerPassword;
        if (1 === preg_match('/[\r\n]/', $userPassword.$ownerPassword)) {
            throw new EngineException('PDF passwords must not contain line breaks.');
        }

        // qpdf < 11.7 only takes positional passwords, which it reads as options when they start with "-"
        $named = version_compare($this->version(), '11.7', '>=');
        if (!$named && (str_starts_with($userPassword, '-') || str_starts_with($ownerPassword, '-'))) {
            throw new EngineException('PDF passwords starting with "-" require qpdf 11.7 or later.');
        }

        $this->withDirectory(function (WorkingDirectory $directory) use ($input, $output, $userPassword, $ownerPassword, $named): void {
            $source = $this->stage($directory, $input, 'input');
            $result = $this->outputPath($directory);
            $encrypt = $named
                ? ['--encrypt', '--user-password='.$userPassword, '--owner-password='.$ownerPassword, '--bits=256', '--']
                : ['--encrypt', $userPassword, $ownerPassword, '256', '--'];
            // passwords go through an argument file to stay out of the process list, .json for the AppArmor profile
            $argumentFile = $directory->writeFile('arguments.json', implode("\n", [$source, $result, ...$encrypt])."\n");
            try {
                $this->runner->run([$this->binary(), '@'.$argumentFile], self::SUCCESS_CODES, null, [$this->binary(), $source, $result, '--encrypt', '***', '***', '256', '--']);
            } catch (EngineException $e) {
                // qpdf may echo a password in its error output
                throw new EngineException(str_replace(array_filter([$userPassword, $ownerPassword], static fn (string $p): bool => '' !== $p), '***', $e->getMessage()), 0, $e);
            }
            $this->move($result, $output);
        });
    }

    private function version(): string
    {
        if (null === $this->version) {
            $output = $this->runner->run([$this->binary(), '--version'], self::SUCCESS_CODES);
            $this->version = 1 === preg_match('/qpdf version (\d+(?:\.\d+)*)/', $output, $matches) ? $matches[1] : '0';
        }

        return $this->version;
    }

    /**
     * @param list<string> $files files to attach, named after their base name
     */
    public function embed(string $input, string $output, array $files): void
    {
        $this->withDirectory(function (WorkingDirectory $directory) use ($input, $output, $files): void {
            $command = [$this->binary(), $this->stage($directory, $input, 'input')];
            $result = $this->outputPath($directory);
            $command[] = $result;

            $index = 0;
            $names = [];
            foreach ($files as $file) {
                $name = self::fileName($file);
                if (isset($names[$name])) {
                    throw new EngineException(\sprintf('Two embedded files are named "%s", rename one of them.', $name));
                }
                $names[$name] = true;
                $command[] = '--add-attachment';
                $command[] = $this->stage($directory, $file, 'attachment_'.$index++);
                $command[] = '--key='.$name;
                $command[] = '--filename='.$name;
                $mimeType = self::MIME_TYPES[strtolower(pathinfo($name, \PATHINFO_EXTENSION))] ?? null;
                if (null !== $mimeType) {
                    $command[] = '--mimetype='.$mimeType;
                }
                $command[] = '--replace';
                $command[] = '--';
            }

            $this->runner->run($command, self::SUCCESS_CODES);
            $this->move($result, $output);
        });
    }

    /**
     * Draws the first page of $stampPdf over (or under) every selected page, scaled to fit and centered.
     *
     * @param string|null $pages page ranges ("1-3,5"), null for all pages
     */
    public function overlay(string $input, string $output, string $stampPdf, bool $underlay, string|null $pages = null): void
    {
        $this->withDirectory(function (WorkingDirectory $directory) use ($input, $output, $stampPdf, $underlay, $pages): void {
            $source = $this->stage($directory, $input, 'input');
            $stamp = $this->stage($directory, $stampPdf, 'stamp');
            $to = null !== $pages && '' !== trim($pages) ? $this->selectPages($source, $pages) : '1-z';
            $result = $this->outputPath($directory);
            $this->runner->run([
                $this->binary(), $source, $result,
                $underlay ? '--underlay' : '--overlay', $stamp, '--to='.$to, '--from=', '--repeat=1', '--',
            ], self::SUCCESS_CODES);
            $this->move($result, $output);
        });
    }

    public function pageCount(string $input): int
    {
        return $this->withDirectory(fn (WorkingDirectory $directory): int => $this->countPages($this->stage($directory, $input, 'input')));
    }

    /**
     * Visible size of a page in PDF points, as displayed (CropBox, else MediaBox, swapped when the page is rotated by 90 or 270 degrees).
     *
     * @return array{0: float, 1: float} width, height
     */
    public function pageSize(string $input, int $page = 1): array
    {
        return $this->withDirectory(function (WorkingDirectory $directory) use ($input, $page): array {
            $source = $this->stage($directory, $input, 'input');
            $pages = $this->json([$this->binary(), '--json=2', '--json-key=pages', $source])['pages'] ?? null;
            $ref = \is_array($pages) && \is_array($pages[$page - 1] ?? null) ? ($pages[$page - 1]['object'] ?? null) : null;
            if (!\is_string($ref)) {
                throw new EngineException(\sprintf('The PDF has no page %d.', $page));
            }

            $box = null;
            $rotate = null;
            // MediaBox, CropBox and Rotate are inheritable from the parent page tree nodes
            for ($depth = 0; \is_string($ref) && $depth < 32 && (null === $box || null === $rotate); ++$depth) {
                $object = $this->json([$this->binary(), '--json=2', '--json-key=qpdf', '--json-object='.$ref, $source])['qpdf'][1]['obj:'.$ref]['value'] ?? null;
                if (!\is_array($object)) {
                    break;
                }
                $box ??= $this->resolveBox($object['/CropBox'] ?? null, $source) ?? $this->resolveBox($object['/MediaBox'] ?? null, $source);
                $rotate ??= \is_int($object['/Rotate'] ?? null) ? $object['/Rotate'] : null;
                $ref = $object['/Parent'] ?? null;
            }

            [$width, $height] = $box ?? [612.0, 792.0];

            return 90 === abs(($rotate ?? 0) % 180) ? [$height, $width] : [$width, $height];
        });
    }

    /**
     * Reads the document outline. Items whose destination is not a page of the document are dropped, their children are kept.
     *
     * @return list<array{title: string, page: int, children?: list<mixed>}>
     */
    public function readBookmarks(string $input): array
    {
        return $this->withDirectory(function (WorkingDirectory $directory) use ($input): array {
            $outlines = $this->json([$this->binary(), '--json=2', '--json-key=outlines', $this->stage($directory, $input, 'input')])['outlines'] ?? [];

            return \is_array($outlines) ? self::toBookmarks($outlines) : [];
        });
    }

    /**
     * @param array<mixed> $items
     *
     * @return list<array{title: string, page: int, children?: list<mixed>}>
     */
    private static function toBookmarks(array $items): array
    {
        $bookmarks = [];
        foreach ($items as $item) {
            if (!\is_array($item)) {
                continue;
            }
            $children = \is_array($item['kids'] ?? null) ? self::toBookmarks($item['kids']) : [];
            $page = $item['destpageposfrom1'] ?? null;
            if (!\is_int($page) || $page < 1) {
                array_push($bookmarks, ...$children);
                continue;
            }
            $bookmark = ['title' => \is_string($item['title'] ?? null) ? $item['title'] : '', 'page' => $page];
            if ([] !== $children) {
                $bookmark['children'] = $children;
            }
            $bookmarks[] = $bookmark;
        }

        return $bookmarks;
    }

    /**
     * Replaces the document outline. The pages are 1-based.
     *
     * @param list<array{title: string, page: int, children?: list<mixed>}> $bookmarks
     */
    public function addBookmarks(string $input, string $output, array $bookmarks): void
    {
        $this->withDirectory(function (WorkingDirectory $directory) use ($input, $output, $bookmarks): void {
            $source = $this->stage($directory, $input, 'input');

            $document = $this->json([$this->binary(), '--json=2', '--json-key=pages', '--json-key=qpdf', '--json-object=trailer', $source]);
            $maxObjectId = $document['qpdf'][0]['maxobjectid'] ?? null;
            $rootRef = $document['qpdf'][1]['trailer']['value']['/Root'] ?? null;
            $pages = $document['pages'] ?? null;
            if (!\is_int($maxObjectId) || !\is_string($rootRef) || !\is_array($pages)) {
                throw new EngineException('Unable to read the PDF structure from the qpdf JSON output.');
            }
            $pageRefs = [];
            foreach ($pages as $page) {
                $pageRefs[] = \is_array($page) && \is_string($page['object'] ?? null) ? $page['object'] : throw new EngineException('Unable to read the PDF pages from the qpdf JSON output.');
            }

            $catalog = $this->json([$this->binary(), '--json=2', '--json-key=qpdf', '--json-object='.$rootRef, $source])['qpdf'][1]['obj:'.$rootRef]['value'] ?? null;
            if (!\is_array($catalog)) {
                throw new EngineException('Unable to read the PDF catalog from the qpdf JSON output.');
            }

            $objects = [];
            $nextId = $maxObjectId + 1;
            $outlinesRef = ($nextId++).' 0 R';
            [$first, $last, $count] = $this->buildOutlineItems($bookmarks, $outlinesRef, $pageRefs, $objects, $nextId);

            unset($catalog['/Outlines']);
            if (null !== $first) {
                $objects[$outlinesRef] = ['/Type' => '/Outlines', '/First' => $first, '/Last' => $last, '/Count' => $count];
                $catalog['/Outlines'] = $outlinesRef;
                $catalog['/PageMode'] = '/UseOutlines';
            } elseif ('/UseOutlines' === ($catalog['/PageMode'] ?? null)) {
                unset($catalog['/PageMode']);
            }
            $objects[$rootRef] = $catalog;

            $update = [];
            foreach ($objects as $ref => $value) {
                $update['obj:'.$ref] = ['value' => $value];
            }
            try {
                $json = json_encode(['qpdf' => [['jsonversion' => 2, 'pushedinheritedpageresources' => false, 'calledgetallpages' => false, 'maxobjectid' => $maxObjectId], $update]], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
            } catch (\JsonException $e) {
                throw new EngineException('Unable to encode the bookmarks: '.$e->getMessage(), 0, $e);
            }

            $updateFile = $directory->writeFile('bookmarks.json', $json);
            $result = $this->outputPath($directory);
            $this->runner->run([$this->binary(), $source, $result, '--update-from-json='.$updateFile], self::SUCCESS_CODES);
            $this->move($result, $output);
        });
    }

    /**
     * @param list<mixed>                 $bookmarks
     * @param list<string>                $pageRefs
     * @param array<string, array<mixed>> $objects   receives the new outline item objects
     *
     * @return array{0: string|null, 1: string|null, 2: int} first item, last item, number of visible descendants
     */
    private function buildOutlineItems(array $bookmarks, string $parentRef, array $pageRefs, array &$objects, int &$nextId): array
    {
        $refs = [];
        foreach ($bookmarks as $bookmark) {
            if (!\is_array($bookmark) || !\is_string($bookmark['title'] ?? null) || !\is_int($bookmark['page'] ?? null)) {
                throw new EngineException('Each bookmark must be an array with a string "title" and an integer "page".');
            }
            $refs[] = ($nextId++).' 0 R';
        }

        $count = 0;
        foreach ($bookmarks as $index => $bookmark) {
            /** @var array{title: string, page: int, children?: mixed} $bookmark */
            $page = $bookmark['page'];
            if ($page < 1 || $page > \count($pageRefs)) {
                throw new EngineException(\sprintf('The bookmark "%s" targets page %d, the document has %d page(s).', $bookmark['title'], $page, \count($pageRefs)));
            }

            $item = [
                '/Title' => 'u:'.$bookmark['title'],
                '/Parent' => $parentRef,
                '/Dest' => [$pageRefs[$page - 1], '/XYZ', null, null, null],
            ];
            if ($index > 0) {
                $item['/Prev'] = $refs[$index - 1];
            }
            if (isset($refs[$index + 1])) {
                $item['/Next'] = $refs[$index + 1];
            }

            $children = $bookmark['children'] ?? [];
            if (!\is_array($children)) {
                throw new EngineException(\sprintf('The children of the bookmark "%s" must be a list.', $bookmark['title']));
            }
            [$first, $last, $descendants] = $this->buildOutlineItems(array_values($children), $refs[$index], $pageRefs, $objects, $nextId);
            if (null !== $first) {
                $item['/First'] = $first;
                $item['/Last'] = $last;
                $item['/Count'] = $descendants;
            }

            $objects[$refs[$index]] = $item;
            $count += 1 + $descendants;
        }

        return [$refs[0] ?? null, [] !== $refs ? $refs[\count($refs) - 1] : null, $count];
    }

    /**
     * @return array{0: float, 1: float}|null
     */
    private function resolveBox(mixed $box, string $source): array|null
    {
        if (\is_string($box) && 1 === preg_match('/^\d+ \d+ R$/', $box)) {
            $box = $this->json([$this->binary(), '--json=2', '--json-key=qpdf', '--json-object='.$box, $source])['qpdf'][1]['obj:'.$box]['value'] ?? null;
        }
        if (!\is_array($box) || 4 !== \count($box)) {
            return null;
        }

        $numbers = array_map(static fn (mixed $value): float => is_numeric($value) ? (float) $value : 0.0, array_values($box));
        $width = abs($numbers[2] - $numbers[0]);
        $height = abs($numbers[3] - $numbers[1]);

        return $width > 0 && $height > 0 ? [$width, $height] : null;
    }

    /**
     * @param list<string> $command
     *
     * @return array<mixed>
     */
    private function json(array $command): array
    {
        try {
            $data = json_decode($this->runner->run($command, self::SUCCESS_CODES), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new EngineException('Unable to decode the qpdf JSON output: '.$e->getMessage(), 0, $e);
        }

        return \is_array($data) ? $data : throw new EngineException('Unexpected qpdf JSON output.');
    }

    private function countPages(string $source): int
    {
        $output = trim($this->runner->run([$this->binary(), '--show-npages', $source], self::SUCCESS_CODES));
        if (1 !== preg_match('/^\d+$/', $output)) {
            throw new EngineException(\sprintf('Unexpected qpdf page count output "%s".', $output));
        }

        return (int) $output;
    }

    private function selectPages(string $source, string $pages): string
    {
        $pageCount = $this->countPages($source);
        $selected = PageRanges::pages($pages, $pageCount);
        if ([] === $selected) {
            throw new EngineException(\sprintf('The page ranges "%s" select no page, the document has %d page(s).', $pages, $pageCount));
        }
        sort($selected);

        return PageRanges::toQpdf($selected);
    }

    private function binary(): string
    {
        return $this->resolvedBinary ??= $this->binaryFinder->find('qpdf', $this->binary);
    }

    /**
     * @template T
     *
     * @param \Closure(WorkingDirectory): T $callback
     *
     * @return T
     */
    private function withDirectory(\Closure $callback): mixed
    {
        $directory = ProcessRunner::createWorkingDirectory($this->tempDir);
        try {
            return $callback($directory);
        } finally {
            $directory->remove($this->logger);
        }
    }

    private function stage(WorkingDirectory $directory, string $file, string $name): string
    {
        if (!is_file($file) || !is_readable($file)) {
            throw new EngineException(\sprintf('The file "%s" does not exist or is not readable.', $file));
        }

        $target = $directory->getPath().\DIRECTORY_SEPARATOR.$name.'.pdf';
        if (@link($file, $target)) {
            return $target;
        }

        try {
            $this->filesystem->copy($file, $target, true);
        } catch (IOExceptionInterface $e) {
            throw new EngineException(\sprintf('Unable to copy "%s": %s', $file, $e->getMessage()), 0, $e);
        }

        return $target;
    }

    private function outputPath(WorkingDirectory $directory): string
    {
        return $directory->getPath().\DIRECTORY_SEPARATOR.'output.pdf';
    }

    private function move(string $source, string $target): void
    {
        try {
            $this->filesystem->mkdir(\dirname($target));
            $this->filesystem->rename($source, $target, true);
        } catch (IOExceptionInterface $e) {
            throw new EngineException(\sprintf('Unable to write "%s": %s', $target, $e->getMessage()), 0, $e);
        }
    }

    private static function fileName(string $path): string
    {
        $normalized = rtrim(str_replace('\\', '/', $path), '/');

        return substr($normalized, (int) strrpos('/'.$normalized, '/'));
    }

    private static function baseName(string $path): string
    {
        $name = self::fileName($path);
        $dot = strrpos($name, '.');

        return false === $dot || 0 === $dot ? $name : substr($name, 0, $dot);
    }
}
