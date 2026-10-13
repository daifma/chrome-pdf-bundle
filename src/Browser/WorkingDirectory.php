<?php

namespace Daif\ChromePdfBundle\Browser;

use Daif\ChromePdfBundle\Exception\BrowserException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * Per-conversion temporary directory holding index.html and its assets.
 *
 * @internal
 */
final class WorkingDirectory
{
    private const REMOVE_ATTEMPTS = 5;
    private const REMOVE_RETRY_DELAY_US = 100_000;

    private readonly Filesystem $filesystem;
    private readonly string $path;

    /** @var array<string, string> basename => real source path */
    private array $copiedAssets = [];

    private function __construct(string $path)
    {
        $this->filesystem = new Filesystem();
        $this->path = $path;
    }

    public static function create(string|null $baseDir = null): self
    {
        $baseDir = Path::canonicalize($baseDir ?? sys_get_temp_dir());

        $path = Path::join($baseDir, 'chrome_pdf_'.bin2hex(random_bytes(8)));
        try {
            (new Filesystem())->mkdir($path, 0o700);
        } catch (IOExceptionInterface $e) {
            throw new BrowserException(\sprintf('Unable to create the conversion directory "%s": %s', $path, $e->getMessage()), 0, $e);
        }

        $realPath = realpath($path);

        return new self(false !== $realPath ? $realPath : $path);
    }

    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @return string absolute path of the written file
     */
    public function writeFile(string $name, string $content): string
    {
        $target = Path::join($this->path, $name);
        try {
            $this->filesystem->dumpFile($target, $content);
        } catch (IOExceptionInterface $e) {
            throw new BrowserException(\sprintf('Unable to write "%s": %s', $target, $e->getMessage()), 0, $e);
        }

        return $target;
    }

    /**
     * Copies the assets next to index.html under their basename.
     *
     * @param array<array-key, mixed> $assets expected SplFileInfo values, validated at runtime
     */
    public function copyAssets(array $assets): void
    {
        foreach ($assets as $key => $fileInfo) {
            if (!$fileInfo instanceof \SplFileInfo) {
                throw new BrowserException(\sprintf('Asset "%s" must be an instance of SplFileInfo.', $key));
            }

            $source = $fileInfo->getPathname();
            $realSource = realpath($source);
            if (false === $realSource || !is_file($realSource)) {
                throw new BrowserException(\sprintf('Asset file "%s" does not exist or is not readable.', $source));
            }

            // basename computed on normalized separators so "C:\a\b.png" gives "b.png" on any OS
            $name = rtrim(str_replace('\\', '/', \is_string($key) && '' !== $key ? $key : $source), '/');
            $basename = substr($name, (int) strrpos('/'.$name, '/'));
            if (\in_array($basename, ['', '.', '..', 'index.html'], true)) {
                throw new BrowserException(\sprintf('Invalid asset name "%s".', $basename));
            }

            if (isset($this->copiedAssets[$basename])) {
                if ($this->copiedAssets[$basename] === $realSource) {
                    continue;
                }

                throw new BrowserException(\sprintf('Two different assets share the same file name "%s" ("%s" and "%s"). Assets are copied next to the HTML file under their base name; rename one of them.', $basename, $this->copiedAssets[$basename], $realSource));
            }

            try {
                $this->filesystem->copy($realSource, Path::join($this->path, $basename), true);
            } catch (IOExceptionInterface $e) {
                throw new BrowserException(\sprintf('Unable to copy asset "%s": %s', $source, $e->getMessage()), 0, $e);
            }
            $this->copiedAssets[$basename] = $realSource;
        }
    }

    /**
     * Removes the directory, retrying a few times (Windows keeps files locked for a short while
     * after Chrome released them). Never throws.
     */
    public function remove(LoggerInterface|null $logger = null): void
    {
        for ($attempt = 1; $attempt <= self::REMOVE_ATTEMPTS; ++$attempt) {
            try {
                $this->filesystem->remove($this->path);

                return;
            } catch (\Throwable $e) {
                if (self::REMOVE_ATTEMPTS === $attempt) {
                    $logger?->warning('Unable to remove the Chrome PDF conversion directory "{path}": {message}', [
                        'path' => $this->path,
                        'message' => $e->getMessage(),
                        'exception' => $e,
                    ]);

                    return;
                }

                usleep(self::REMOVE_RETRY_DELAY_US * $attempt);
            }
        }
    }
}
