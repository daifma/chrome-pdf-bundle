<?php

namespace Daif\ChromePdfBundle\Processor;

use Daif\ChromePdfBundle\Exception\ProcessorException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * @implements ProcessorInterface<\SplFileInfo>
 */
final class FileProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly string $directory,
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    public function __invoke(string|null $fileName): \Generator
    {
        if (null === $fileName) {
            $fileName = 'chrome_pdf_'.bin2hex(random_bytes(8));
            $this->logger?->debug('{processor}: no filename given. Content will be dumped to "{file}".', ['processor' => self::class, 'file' => $fileName]);
        }

        $path = Path::join($this->directory, $fileName);
        if (!Path::isBasePath($this->directory, $path)) {
            throw new ProcessorException(\sprintf('The file name "%s" points outside of the "%s" directory.', $fileName, $this->directory));
        }

        $tempfileProcessor = (new TempfileProcessor())($fileName);

        do {
            $chunk = yield;
            $tempfileProcessor->send($chunk);
        } while (!$chunk->isLast());

        $resource = $tempfileProcessor->getReturn();

        try {
            $this->filesystem->dumpFile($path, $resource);
            $this->logger?->debug('{processor}: content dumped to "{path}".', ['processor' => self::class, 'path' => $path]);

            return new \SplFileInfo($path);
        } catch (\Throwable $t) {
            throw new ProcessorException(\sprintf('Unable to write to "%s".', $fileName), previous : $t);
        } finally {
            fclose($resource);
        }
    }
}
