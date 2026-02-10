<?php

namespace Daif\ChromePdfBundle\Processor;

use Symfony\Contracts\HttpClient\ChunkInterface;

/**
 * @template-covariant T of mixed = mixed
 */
interface ProcessorInterface
{
    /**
     * @return \Generator<int, void, ChunkInterface, T>
     */
    public function __invoke(string|null $fileName): \Generator;
}
