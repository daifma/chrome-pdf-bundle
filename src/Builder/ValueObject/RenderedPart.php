<?php

namespace Daif\ChromePdfBundle\Builder\ValueObject;

use Daif\ChromePdfBundle\Enumeration\Part;

class RenderedPart
{
    public function __construct(
        public readonly Part $type,
        public readonly string $body,
    ) {
    }
}
