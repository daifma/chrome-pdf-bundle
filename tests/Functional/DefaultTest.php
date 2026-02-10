<?php

namespace Daif\ChromePdfBundle\Tests\Functional;

class DefaultTest extends AbstractChromePdfWebTestCase
{
    public function testBundleDefault(): void
    {
        $this->expectNotToPerformAssertions();

        $kernel = static::createKernel(['test_case' => 'Default']);
        $kernel->boot();
    }
}
