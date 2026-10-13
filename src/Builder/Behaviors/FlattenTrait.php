<?php

namespace Daif\ChromePdfBundle\Builder\Behaviors;

use Daif\ChromePdfBundle\Builder\Attributes\WithConfigurationNode;
use Daif\ChromePdfBundle\Builder\BodyBag;
use Daif\ChromePdfBundle\NodeBuilder\BooleanNodeBuilder;

/**
 * Requires qpdf.
 *
 * @package Behavior\\Flatten
 */
trait FlattenTrait
{
    abstract protected function getBodyBag(): BodyBag;

    /**
     * Flattening a PDF combines all its contents into a single layer. (default false).
     *
     * @example flatten() // is same as `->flatten(true)`
     */
    #[WithConfigurationNode(new BooleanNodeBuilder('flatten'))]
    public function flatten(bool $bool = true): static
    {
        $this->getBodyBag()->set('flatten', $bool);

        return $this;
    }
}
