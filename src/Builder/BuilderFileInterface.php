<?php

namespace Daif\ChromePdfBundle\Builder;

use Daif\ChromePdfBundle\Builder\Result\ChromePdfFileResult;

interface BuilderFileInterface extends BuilderInterface
{
    public function generate(): ChromePdfFileResult;
}
