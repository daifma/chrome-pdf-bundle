<?php

namespace Daif\ChromePdfBundle\Builder\Attributes;

use Daif\ChromePdfBundle\NodeBuilder\NodeBuilderInterface;

#[\Attribute(\Attribute::TARGET_METHOD)]
final class WithConfigurationNode
{
    public function __construct(
        public readonly NodeBuilderInterface $node,
    ) {
    }
}
