<?php

namespace Daif\ChromePdfBundle\Tests\Enumeration;

use Daif\ChromePdfBundle\Enumeration\EmulatedMediaType;
use PHPUnit\Framework\TestCase;

class EmulatedMediaTypeTest extends TestCase
{
    public function testCaseListIsCorrect(): void
    {
        $this->assertEquals(
            ['print', 'screen'],
            array_map(
                static fn (EmulatedMediaType $case): string => $case->value,
                EmulatedMediaType::cases(),
            ),
        );
    }
}
