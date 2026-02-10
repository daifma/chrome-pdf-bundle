<?php

namespace Daif\ChromePdfBundle\NodeBuilder;

abstract class NodeBuilder
{
    public function __construct(
        protected string $name,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }
}
