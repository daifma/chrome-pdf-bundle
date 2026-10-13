<?php

namespace Daif\ChromePdfBundle\Tests\Enumeration;

use Daif\ChromePdfBundle\Enumeration\Unit;
use Daif\ChromePdfBundle\Exception\InvalidBuilderConfiguration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UnitTest extends TestCase
{
    public static function itCanBeParsedCorrectlyProvider(): \Generator
    {
        yield '(int) 12' => [12, [12.0, Unit::Inches]];
        yield '(string) 12' => ['12', [12.0, Unit::Inches]];
        yield '12.0' => [12.0, [12.0, Unit::Inches]];
        yield '12.1' => [12.1, [12.1, Unit::Inches]];
        yield '12.1in' => ['12.1in', [12.1, Unit::Inches]];
        yield '12.1pc' => ['12.1pc', [12.1, Unit::Picas]];
        yield 'uppercase unit' => ['12MM', [12.0, Unit::Millimeters]];
        yield 'spaces' => [' 12 pt ', [12.0, Unit::Points]];
        yield 'leading dot' => ['.5cm', [0.5, Unit::Centimeters]];
        yield 'exponent' => ['1e1px', [10.0, Unit::Pixels]];
    }

    /**
     * @param array{float, Unit} $expected
     */
    #[DataProvider('itCanBeParsedCorrectlyProvider')]
    public function testItCanBeParsedCorrectly(string|int|float $raw, array $expected): void
    {
        self::assertSame($expected, Unit::parse($raw));
    }

    /**
     * @return iterable<string, array{string|int|float}>
     */
    public static function invalidValues(): iterable
    {
        yield 'garbage' => ['garbage'];
        yield 'unknown unit' => ['12km'];
        yield 'negative string' => ['-1in'];
        yield 'negative number' => [-1];
        yield 'empty' => [''];
        yield 'two numbers' => ['1 2in'];
        yield 'infinite' => [\INF];
    }

    #[DataProvider('invalidValues')]
    public function testInvalidValuesAreRejected(string|int|float $raw): void
    {
        $this->expectException(InvalidBuilderConfiguration::class);

        Unit::parse($raw);
    }

    /**
     * @return iterable<string, array{string, float}>
     */
    public static function inches(): iterable
    {
        yield 'in' => ['2in', 2.0];
        yield 'pt' => ['72pt', 1.0];
        yield 'px' => ['96px', 1.0];
        yield 'mm' => ['25.4mm', 1.0];
        yield 'cm' => ['2.54cm', 1.0];
        yield 'pc' => ['6pc', 1.0];
    }

    #[DataProvider('inches')]
    public function testParseToInches(string $raw, float $expected): void
    {
        self::assertEqualsWithDelta($expected, Unit::parseToInches($raw), 1e-9);
    }
}
