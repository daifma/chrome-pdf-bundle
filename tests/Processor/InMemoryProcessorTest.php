<?php

namespace Daif\ChromePdfBundle\Tests\Processor;

use Daif\ChromePdfBundle\Processor\InMemoryProcessor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Chunk\DataChunk;
use Symfony\Component\HttpClient\Chunk\FirstChunk;
use Symfony\Component\HttpClient\Chunk\LastChunk;

class InMemoryProcessorTest extends TestCase
{
    public function testProcess(): void
    {
        $processor = new InMemoryProcessor();
        $generator = $processor(null);

        $generator->send(new FirstChunk(0, 'a'));
        $generator->send(new DataChunk(1, 'b'));
        $generator->send(new LastChunk(2, 'c'));

        $return = $generator->getReturn();

        self::assertIsString($return); // @phpstan-ignore staticMethod.alreadyNarrowedType
        self::assertSame('abc', $return);
    }
}
