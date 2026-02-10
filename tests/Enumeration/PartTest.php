<?php

namespace Daif\ChromePdfBundle\Tests\Enumeration;

use Daif\ChromePdfBundle\Enumeration\Part;
use PHPUnit\Framework\TestCase;

class PartTest extends TestCase
{
    public function testCaseListIsCorrect(): void
    {
        $this->assertEquals(
            ['header.html', 'index.html', 'footer.html'],
            array_map(
                static fn (Part $case): string => $case->value,
                Part::cases(),
            ),
        );
    }
}
