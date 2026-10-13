<?php

namespace Daif\ChromePdfBundle\Builder;

interface BuilderAsyncInterface extends BuilderInterface
{
    public function generateAsync(): void;
}
