<?php

namespace Daif\ChromePdfBundle\Bridge\LeagueFlysystem\Processor;

use Daif\ChromePdfBundle\Exception\ProcessorException;
use Daif\ChromePdfBundle\Processor\ProcessorInterface;
use Daif\ChromePdfBundle\Processor\TempfileProcessor;
use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;

/**
 * @implements ProcessorInterface<(\Closure(): string)>
 */
final class FlysystemProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly FilesystemOperator $filesystemOperator,
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    public function __invoke(string|null $fileName): \Generator
    {
        if (null === $fileName) {
            $fileName = uniqid('chrome_pdf_', true);
        }

        $tmpfileProcessor = (new TempfileProcessor())($fileName);

        do {
            $chunk = yield;
            $tmpfileProcessor->send($chunk);
        } while (!$chunk->isLast());

        $tmpfile = $tmpfileProcessor->getReturn();

        try {
            $this->filesystemOperator->writeStream($fileName, $tmpfile);

            $this->logger?->debug('{processor}: content dumped to "{file}".', ['processor' => self::class, 'file' => $fileName]);

            return function () use ($fileName) {
                return $this->filesystemOperator->read($fileName); // use readStream instead ?
            };
        } catch (\Throwable $t) {
            throw new ProcessorException(\sprintf('Unable to write to "%s".', $fileName), previous : $t);
        } finally {
            fclose($tmpfile);
        }
    }
}
