<?php

namespace Daif\ChromePdfBundle\Builder\ValueObject;

use Daif\ChromePdfBundle\Enumeration\Part;

/**
 * A part (body, header, footer) read from a local file. Stores the path only so the body bag stays serializable.
 */
final class FilePart
{
    public function __construct(
        public readonly Part $type,
        public readonly string $path,
    ) {
    }
}
