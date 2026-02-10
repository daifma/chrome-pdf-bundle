<?php

namespace Daif\ChromePdfBundle\Tests\Enumeration;

use Daif\ChromePdfBundle\Enumeration\PaperSize;
use Daif\ChromePdfBundle\Enumeration\PaperSizeInterface;
use Daif\ChromePdfBundle\Enumeration\Unit;
use PHPUnit\Framework\TestCase;

final class PaperSizeTest extends TestCase
{
    public function testCaseListIsCorrect(): void
    {
        $this->assertEquals(
            ['letter', 'legal', 'tabloid', 'ledger', 'A0', 'A1', 'A2', 'A3', 'A4', 'A5', 'A6'],
            array_map(
                static fn (PaperSize $case): string => $case->value,
                PaperSize::cases(),
            ),
        );
    }

    public function testItImplementsPaperSizeInterface(): void
    {
        $this->assertTrue(is_a(PaperSize::class, PaperSizeInterface::class, true)); // @phpstan-ignore function.alreadyNarrowedType
    }

    public function testUnitIsAlwaysInches(): void
    {
        foreach (PaperSize::cases() as $size) {
            self::assertSame(Unit::Inches, $size->unit());
        }
    }

    public function testEveryCasesHasWidth(): void
    {
        foreach (PaperSize::cases() as $size) {
            $size->width(); // @phpstan-ignore method.resultUnused
            self::addToAssertionCount(1);
        }
    }

    public function testEveryCasesHasHeight(): void
    {
        foreach (PaperSize::cases() as $size) {
            $size->height(); // @phpstan-ignore method.resultUnused
            self::addToAssertionCount(1);
        }
    }
}
