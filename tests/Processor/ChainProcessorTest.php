<?php

namespace Daif\ChromePdfBundle\Tests\Processor;

use Daif\ChromePdfBundle\Processor\ChainProcessor;
use Daif\ChromePdfBundle\Processor\ProcessorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Chunk\DataChunk;
use Symfony\Component\HttpClient\Chunk\FirstChunk;
use Symfony\Component\HttpClient\Chunk\LastChunk;

class ChainProcessorTest extends TestCase
{
    public function testProcess(): void
    {
        $processor1 = $this->mockProcessor('first: ');
        $processor2 = $this->mockProcessor('second: ');
        $processor = new ChainProcessor([$processor1, $processor2]);
        $generator = $processor(null);

        $generator->send(new FirstChunk(0, 'a'));
        $generator->send(new DataChunk(1, 'b'));
        $generator->send(new LastChunk(2, 'c'));

        $return = $generator->getReturn();

        self::assertSame(['first: abc', 'second: abc'], $return);
    }

    /**
     * @return ProcessorInterface<string>
     */
    private function mockProcessor(string $prefix): ProcessorInterface
    {
        return new class($prefix) implements ProcessorInterface {
            public function __construct(private readonly string $prefix)
            {
            }

            public function __invoke(string|null $fileName): \Generator
            {
                $content = $this->prefix;
                do {
                    $chunk = yield;
                    $content .= $chunk->getContent();
                } while (!$chunk->isLast());

                return $content;
            }
        };
    }
}
