<?php

namespace Daif\ChromePdfBundle\Tests\Engine;

use Daif\ChromePdfBundle\Engine\PageRanges;
use Daif\ChromePdfBundle\Exception\EngineException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PageRangesTest extends TestCase
{
    /**
     * @param list<list<int>> $expected
     */
    #[DataProvider('provideRanges')]
    public function testParse(string $ranges, array $expected): void
    {
        self::assertSame($expected, PageRanges::parse($ranges, 6));
    }

    /**
     * @return iterable<string, array{string, list<list<int>>}>
     */
    public static function provideRanges(): iterable
    {
        yield 'single page' => ['2', [[2]]];
        yield 'ranges' => ['1-3, 5', [[1, 2, 3], [5]]];
        yield 'open end' => ['4-', [[4, 5, 6]]];
        yield 'open start' => ['-2', [[1, 2]]];
        yield 'last page' => ['l', [[6]]];
        yield 'to last page' => ['5-z', [[5, 6]]];
        yield 'odd' => ['odd', [[1, 3, 5]]];
        yield 'even' => ['even', [[2, 4, 6]]];
        yield 'descending' => ['3-1', [[3, 2, 1]]];
        yield 'beyond the document' => ['5-9,8', [[5, 6]]];
    }

    public function testPagesAreUniqueInSelectionOrder(): void
    {
        self::assertSame([3, 4, 1], PageRanges::pages('3-4,1,4', 6));
    }

    public function testToQpdf(): void
    {
        self::assertSame('1-3,5,7-8,2', PageRanges::toQpdf([1, 2, 3, 5, 7, 8, 2]));
        self::assertSame('', PageRanges::toQpdf([]));
    }

    #[DataProvider('provideInvalidRanges')]
    public function testInvalid(string $ranges): void
    {
        $this->expectException(EngineException::class);

        PageRanges::parse($ranges, 6);
    }

    /**
     * @return iterable<array{string}>
     */
    public static function provideInvalidRanges(): iterable
    {
        yield [''];
        yield ['0'];
        yield ['a-b'];
        yield ['-'];
        yield ['1-2-3'];
        yield ['!2'];
    }
}
