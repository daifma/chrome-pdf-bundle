<?php

namespace Daif\ChromePdfBundle\Processor;

use Psr\Log\LoggerInterface;

/**
 * DO NOT USE THIS IN PRODUCTION.
 * This is not memory safe and you might end up with a "Fatal Error: Allowed Memory Size".
 * Consider using one of the other {@see ProcessorInterface}.
 *
 * @implements ProcessorInterface<string>
 */
final class InMemoryProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    public function __invoke(string|null $fileName): \Generator
    {
        $this->logger?->debug('{processor}: starting.', ['processor' => self::class]);

        if (null !== $fileName) {
            $this->logger?->debug('{processor}: Ignoring filename "{file}".', ['processor' => self::class, 'file' => $fileName]);
        }

        $file = '';

        do {
            $chunk = yield;
            $file .= $chunk->getContent();
        } while (!$chunk->isLast());

        $this->logger?->debug('{processor}: finished.', ['processor' => self::class]);

        return $file;
    }
}
