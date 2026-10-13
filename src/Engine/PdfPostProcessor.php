<?php

namespace Daif\ChromePdfBundle\Engine;

use Daif\ChromePdfBundle\Browser\BrowserInterface;
use Daif\ChromePdfBundle\Exception\EngineException;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Applies the Gotenberg "PDF engines" features to a produced PDF, in Gotenberg order:
 * split, PDF/A and PDF/UA, metadata, flatten, rotate, watermark, stamp, bookmarks, embeds, encrypt.
 *
 * Text and image watermarks/stamps are rendered by Chrome as a transparent one-page PDF of the size
 * of the first page, then laid under (watermark) or over (stamp) the selected pages with qpdf.
 * Supported stamp options: font (CSS font family), points (font size, default 48), color (CSS color,
 * default #808080), opacity (0 to 1, default 0.5 for a watermark, 1 for a stamp), rotation (degrees
 * counterclockwise, default 45 for a watermark, 0 for a stamp) and scale (image width relative to the
 * page width, default 0.5). Other pdfcpu options are ignored.
 */
final class PdfPostProcessor
{
    private readonly Filesystem $filesystem;

    /**
     * @param string|null $tempDir unused, every file is written in the $workDir given to process()
     *
     * @phpstan-ignore constructor.unusedParameter
     */
    public function __construct(
        private readonly Qpdf $qpdf,
        private readonly Exiftool $exiftool,
        private readonly LibreOffice $libreOffice,
        private readonly BrowserInterface $browser,
        string|null $tempDir = null,
    ) {
        $this->filesystem = new Filesystem();
    }

    /**
     * The input file is never modified, the produced files are written in $workDir.
     *
     * @param array<string, mixed> $options
     *
     * @return list<string> produced files (more than one only when split without unify)
     */
    public function process(string $pdfFile, array $options, string $workDir): array
    {
        if (!is_file($pdfFile)) {
            throw new EngineException(\sprintf('The file "%s" does not exist.', $pdfFile));
        }

        $files = [$pdfFile];
        if (isset($options['split'])) {
            $split = self::map($options['split'], 'split');
            $files = $this->qpdf->split(
                $pdfFile,
                self::string($split['mode'] ?? null, 'split.mode'),
                self::string($split['span'] ?? null, 'split.span'),
                self::bool($split['unify'] ?? false, 'split.unify'),
                $this->createDirectory($workDir, 'split'),
            );
        }

        $result = [];
        foreach ($files as $file) {
            $result[] = $this->processFile($file, $options, $workDir);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function processFile(string $file, array $options, string $workDir): string
    {
        $directory = $this->createDirectory($workDir, 'post');
        $current = $file;
        $step = 0;
        $next = static function () use ($directory, &$step): string {
            return $directory.\DIRECTORY_SEPARATOR.'step_'.(++$step).'.pdf';
        };

        $pdfFormat = self::nullableString($options['pdfFormat'] ?? null, 'pdfFormat');
        $universalAccess = self::bool($options['pdfUniversalAccess'] ?? false, 'pdfUniversalAccess');
        if (null !== $pdfFormat || $universalAccess) {
            $target = $next();
            $this->libreOffice->convertPdf($current, $target, $pdfFormat, $universalAccess);
            $current = $target;
        }

        $metadata = self::map($options['metadata'] ?? [], 'metadata');
        if ([] !== $metadata) {
            $target = $next();
            $this->copy($current, $target);
            $this->exiftool->writeMetadata($target, $metadata);
            $current = $target;
        }

        if (self::bool($options['flatten'] ?? false, 'flatten')) {
            $target = $next();
            $this->qpdf->flatten($current, $target);
            $current = $target;
        }

        if (isset($options['rotate'])) {
            $rotate = self::map($options['rotate'], 'rotate');
            $angle = self::scalar($rotate['angle'] ?? null);
            if (!is_numeric($angle)) {
                throw new EngineException('The "rotate.angle" option must be 90, 180 or 270.');
            }
            $target = $next();
            $this->qpdf->rotate($current, $target, (int) $angle, self::nullableString($rotate['pages'] ?? null, 'rotate.pages'));
            $current = $target;
        }

        foreach (['watermark' => true, 'stamp' => false] as $name => $underlay) {
            if (isset($options[$name])) {
                $target = $next();
                $this->stamp($current, $target, self::map($options[$name], $name), $underlay, $name, $directory);
                $current = $target;
            }
        }

        $bookmarks = self::map($options['bookmarks'] ?? [], 'bookmarks');
        if ([] !== $bookmarks) {
            $target = $next();
            $this->qpdf->addBookmarks($current, $target, array_values($bookmarks));
            $current = $target;
        }

        $embeds = self::map($options['embeds'] ?? [], 'embeds');
        if ([] !== $embeds) {
            $files = [];
            foreach ($embeds as $embed) {
                $files[] = self::string($embed instanceof \SplFileInfo ? $embed->getPathname() : $embed, 'embeds');
            }
            $target = $next();
            $this->qpdf->embed($current, $target, $files);
            $current = $target;
        }

        if (isset($options['encrypt'])) {
            $encrypt = self::map($options['encrypt'], 'encrypt');
            $target = $next();
            $this->qpdf->encrypt(
                $current,
                $target,
                self::nullableString($encrypt['userPassword'] ?? null, 'encrypt.userPassword'),
                self::nullableString($encrypt['ownerPassword'] ?? null, 'encrypt.ownerPassword'),
            );
            $current = $target;
        }

        if ($current === $file) {
            return $file;
        }

        // keep the original file name, it names the entries of a ZIP when the PDF was split
        $final = $directory.\DIRECTORY_SEPARATOR.self::fileName($file);
        $this->move($current, $final);

        return $final;
    }

    /**
     * @param array<array-key, mixed> $stamp
     */
    private function stamp(string $input, string $output, array $stamp, bool $underlay, string $name, string $directory): void
    {
        $source = self::string(self::scalar($stamp['source'] ?? null), $name.'.source');
        $expression = self::string($stamp['expression'] ?? null, $name.'.expression');
        $pages = self::nullableString($stamp['pages'] ?? null, $name.'.pages');
        $options = self::map($stamp['options'] ?? [], $name.'.options');

        $stampPdf = match ($source) {
            'pdf' => is_file($expression) ? $expression : throw new EngineException(\sprintf('The %s PDF "%s" does not exist.', $name, $expression)),
            'text', 'image' => $this->renderStamp($input, $source, $expression, $options, $underlay, $directory.\DIRECTORY_SEPARATOR.$name.'.pdf'),
            default => throw new EngineException(\sprintf('Invalid %s source "%s", expected "text", "image" or "pdf".', $name, $source)),
        };

        $this->qpdf->overlay($input, $output, $stampPdf, $underlay, $pages);
    }

    /**
     * @param array<array-key, mixed> $options
     */
    private function renderStamp(string $target, string $source, string $expression, array $options, bool $watermark, string $output): string
    {
        [$width, $height] = $this->qpdf->pageSize($target);

        $points = self::number($options['points'] ?? 48, 'points');
        $opacity = max(0.0, min(1.0, self::number($options['opacity'] ?? ($watermark ? 0.5 : 1.0), 'opacity')));
        $rotation = self::number($options['rotation'] ?? ($watermark ? 45 : 0), 'rotation');
        $scale = max(0.01, min(1.0, self::number($options['scale'] ?? 0.5, 'scale')));
        $color = self::cssValue(self::string($options['color'] ?? '#808080', 'color'));
        $font = self::cssValue(self::string($options['font'] ?? 'Helvetica, Arial, sans-serif', 'font'));

        $style = \sprintf('opacity: %s; transform: rotate(%sdeg);', self::css($opacity), self::css(-$rotation));
        $assets = [];
        if ('image' === $source) {
            if (!is_file($expression)) {
                throw new EngineException(\sprintf('The stamp image "%s" does not exist.', $expression));
            }
            $imageName = 'stamp_image.'.strtolower(pathinfo($expression, \PATHINFO_EXTENSION));
            $assets[$imageName] = new \SplFileInfo($expression);
            $content = \sprintf('<img src="%s" style="width: %spt; %s" alt="">', htmlspecialchars($imageName, \ENT_QUOTES), self::css($width * $scale), $style);
        } else {
            $content = \sprintf(
                '<div style="font-family: %s; font-size: %spt; color: %s; white-space: pre; line-height: 1.2; text-align: center; %s">%s</div>',
                htmlspecialchars($font, \ENT_QUOTES),
                self::css($points),
                htmlspecialchars($color, \ENT_QUOTES),
                $style,
                htmlspecialchars($expression, \ENT_QUOTES),
            );
        }

        $html = \sprintf(
            '<!DOCTYPE html><html><head><meta charset="utf-8"><style>@page { size: %1$spt %2$spt; margin: 0; } html, body { margin: 0; padding: 0; background: transparent; }'
            .' .page { position: fixed; top: 0; left: 0; width: %1$spt; height: %2$spt; display: flex; align-items: center; justify-content: center; overflow: hidden; }</style></head>'
            .'<body><div class="page">%3$s</div></body></html>',
            self::css($width),
            self::css($height),
            $content,
        );

        $pdf = $this->browser->htmlToPdf($html, [
            'paperWidth' => $width / 72,
            'paperHeight' => $height / 72,
            'marginTop' => 0,
            'marginBottom' => 0,
            'marginLeft' => 0,
            'marginRight' => 0,
            'printBackground' => true,
            'pageRanges' => '1',
        ], [
            'omitBackground' => true,
            'assets' => $assets,
        ]);

        try {
            $this->filesystem->dumpFile($output, $pdf);
        } catch (IOExceptionInterface $e) {
            throw new EngineException(\sprintf('Unable to write "%s": %s', $output, $e->getMessage()), 0, $e);
        }

        return $output;
    }

    private function createDirectory(string $workDir, string $prefix): string
    {
        $directory = rtrim($workDir, '\\/').\DIRECTORY_SEPARATOR.$prefix.'_'.bin2hex(random_bytes(6));
        try {
            $this->filesystem->mkdir($directory, 0o700);
        } catch (IOExceptionInterface $e) {
            throw new EngineException(\sprintf('Unable to create the directory "%s": %s', $directory, $e->getMessage()), 0, $e);
        }

        return $directory;
    }

    private function copy(string $source, string $target): void
    {
        try {
            $this->filesystem->copy($source, $target, true);
        } catch (IOExceptionInterface $e) {
            throw new EngineException(\sprintf('Unable to copy "%s": %s', $source, $e->getMessage()), 0, $e);
        }
    }

    private function move(string $source, string $target): void
    {
        try {
            $this->filesystem->rename($source, $target, true);
        } catch (IOExceptionInterface $e) {
            throw new EngineException(\sprintf('Unable to write "%s": %s', $target, $e->getMessage()), 0, $e);
        }
    }

    /**
     * Unwraps backed enums (SplitMode, RotateAngle, StampSource...).
     */
    private static function scalar(mixed $value): mixed
    {
        return $value instanceof \BackedEnum ? $value->value : $value;
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function map(mixed $value, string $name): array
    {
        return \is_array($value) ? $value : throw new EngineException(\sprintf('The "%s" option must be an array, "%s" given.', $name, get_debug_type($value)));
    }

    private static function string(mixed $value, string $name): string
    {
        $value = self::scalar($value);

        return \is_string($value) || $value instanceof \Stringable ? (string) $value : throw new EngineException(\sprintf('The "%s" option must be a string, "%s" given.', $name, get_debug_type($value)));
    }

    private static function nullableString(mixed $value, string $name): string|null
    {
        $value = self::scalar($value);

        return null === $value || '' === $value ? null : self::string($value, $name);
    }

    private static function bool(mixed $value, string $name): bool
    {
        return \is_bool($value) ? $value : throw new EngineException(\sprintf('The "%s" option must be a boolean, "%s" given.', $name, get_debug_type($value)));
    }

    private static function number(mixed $value, string $name): float
    {
        return is_numeric($value) ? (float) $value : throw new EngineException(\sprintf('The "%s" stamp option must be a number, "%s" given.', $name, get_debug_type($value)));
    }

    /**
     * Locale independent CSS number.
     */
    private static function css(float $value): string
    {
        return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
    }

    /**
     * Keeps a CSS value inside its declaration.
     */
    private static function cssValue(string $value): string
    {
        return trim(str_replace([';', '{', '}', '<', '>', '\\'], '', $value));
    }

    private static function fileName(string $path): string
    {
        $normalized = rtrim(str_replace('\\', '/', $path), '/');

        return substr($normalized, (int) strrpos('/'.$normalized, '/'));
    }
}
