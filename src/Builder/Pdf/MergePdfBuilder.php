<?php

namespace Daif\ChromePdfBundle\Builder\Pdf;

use Daif\ChromePdfBundle\Builder\AbstractBuilder;
use Daif\ChromePdfBundle\Builder\Attributes\WithBuilderConfiguration;
use Daif\ChromePdfBundle\Builder\Behaviors\BookmarksTrait;
use Daif\ChromePdfBundle\Builder\Behaviors\DownloadFromTrait;
use Daif\ChromePdfBundle\Builder\Behaviors\EmbedTrait;
use Daif\ChromePdfBundle\Builder\Behaviors\EncryptTrait;
use Daif\ChromePdfBundle\Builder\Behaviors\FilesTrait;
use Daif\ChromePdfBundle\Builder\Behaviors\FlattenTrait;
use Daif\ChromePdfBundle\Builder\Behaviors\MetadataTrait;
use Daif\ChromePdfBundle\Builder\Behaviors\PdfFormatTrait;
use Daif\ChromePdfBundle\Builder\Behaviors\StampTrait;
use Daif\ChromePdfBundle\Builder\Behaviors\WatermarkTrait;
use Daif\ChromePdfBundle\Builder\Behaviors\WebhookTrait;
use Daif\ChromePdfBundle\Exception\MissingRequiredFieldException;
use Daif\ChromePdfBundle\Formatter\PathHelper;

/**
 * Merge `n` pdf files into a single one. Requires qpdf.
 *
 * Like Gotenberg, the files are merged in the alphanumerical order of their file names.
 *
 * @methodDoc files Add PDF files to merge.
 * As assets files, by default the PDF files are fetch in the assets folder
 * of your application. For more information about path resolution go to
 * assets documentation.
 *
 * @example files('document.pdf','document_2.pdf')
 *
 * @phpstan-import-type Bookmark from BookmarksTrait
 */
#[WithBuilderConfiguration(type: 'pdf', name: 'merge')]
final class MergePdfBuilder extends AbstractBuilder
{
    use BookmarksTrait;
    use DownloadFromTrait;
    use EmbedTrait;
    use EncryptTrait;
    use FilesTrait;
    use FlattenTrait;
    use MetadataTrait;
    use PdfFormatTrait;
    use StampTrait;
    use WatermarkTrait;
    use WebhookTrait;

    private const AVAILABLE_EXTENSIONS = [
        'pdf',
    ];

    /** @var array<string, int> page count of each merged file, by the name given to files() */
    private array $pageCounts = [];

    /**
     * @var array<string, list<Bookmark>>
     */
    private array $existingBookmarks = [];

    protected function getAllowedFilesExtensions(): array
    {
        return self::AVAILABLE_EXTENSIONS;
    }

    protected function produce(string $workDir): array
    {
        $files = self::sortFiles($this->getBodyBag()->get('files', []));
        $qpdf = $this->getQpdf();

        $this->pageCounts = [];
        $this->existingBookmarks = [];
        $autoIndex = true === $this->getBodyBag()->get('autoIndexBookmarks');
        if ($this->needsPageCounts()) {
            foreach ($files as $name => $path) {
                $this->pageCounts[$name] = $qpdf->pageCount($path);
                if ($autoIndex) {
                    $this->existingBookmarks[$name] = $qpdf->readBookmarks($path);
                }
            }
        }

        $output = $workDir.\DIRECTORY_SEPARATOR.'merged.pdf';
        $qpdf->merge(array_values($files), $output);

        return [$output];
    }

    protected function collectPostProcessOptions(): array
    {
        $options = parent::collectPostProcessOptions();
        unset($options['bookmarks']);

        $bookmarks = $this->resolveBookmarks();
        if ([] !== $bookmarks) {
            $options['bookmarks'] = $bookmarks;
        }

        return $options;
    }

    protected function validatePayloadBody(): void
    {
        if ($this->getBodyBag()->get('files') === null && [] === $this->getBodyBag()->get('downloadFrom', [])) {
            throw new MissingRequiredFieldException('At least one PDF file is required.');
        }
    }

    private function needsPageCounts(): bool
    {
        $bookmarks = $this->getBodyBag()->get('bookmarks');

        return true === $this->getBodyBag()->get('autoIndexBookmarks') || (\is_array($bookmarks) && [] !== $bookmarks && !array_is_list($bookmarks));
    }

    /**
     * Builds the bookmarks of the merged file: for each file, its own bookmarks with autoIndexBookmarks() then the
     * bookmarks given for it, shifted to the position of the file; then the bookmarks given as a list (already absolute).
     *
     * @return list<Bookmark>
     */
    private function resolveBookmarks(): array
    {
        $bookmarks = $this->getBodyBag()->get('bookmarks', []);
        if (!\is_array($bookmarks)) {
            return [];
        }

        $autoIndex = true === $this->getBodyBag()->get('autoIndexBookmarks');
        if (array_is_list($bookmarks) && !$autoIndex) {
            return $bookmarks;
        }

        $perFile = array_is_list($bookmarks) ? [] : $bookmarks;
        $resolved = [];
        $offset = 0;
        foreach ($this->pageCounts as $name => $pageCount) {
            $fileBookmarks = $perFile[$name] ?? $perFile[PathHelper::basename($name)] ?? [];
            $shifted = self::shiftBookmarks(\is_array($fileBookmarks) ? $fileBookmarks : [], $offset);

            if ($autoIndex) {
                array_push($resolved, ...self::shiftBookmarks($this->existingBookmarks[$name] ?? [], $offset));
            }
            array_push($resolved, ...$shifted);

            $offset += $pageCount;
        }

        if (array_is_list($bookmarks)) {
            array_push($resolved, ...$bookmarks);
        }

        return $resolved;
    }

    /**
     * @param array<mixed> $bookmarks
     *
     * @return list<Bookmark>
     */
    private static function shiftBookmarks(array $bookmarks, int $offset): array
    {
        $shifted = [];
        foreach ($bookmarks as $bookmark) {
            $bookmark['page'] += $offset;
            if (isset($bookmark['children'])) {
                $bookmark['children'] = self::shiftBookmarks($bookmark['children'], $offset);
            }
            $shifted[] = $bookmark;
        }

        return $shifted;
    }

    /**
     * @param array<string, string> $files
     *
     * @return array<string, string>
     */
    private static function sortFiles(array $files): array
    {
        uksort($files, static fn (string $a, string $b): int => strnatcasecmp(PathHelper::basename($a), PathHelper::basename($b)));

        return $files;
    }
}
