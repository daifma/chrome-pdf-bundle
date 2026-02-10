<?php

namespace Daif\ChromePdfBundle\Builder;

interface BuilderAssetInterface
{
    public function addAsset(string $path): static;
}
