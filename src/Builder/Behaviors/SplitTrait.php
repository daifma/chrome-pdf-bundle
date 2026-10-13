<?php

namespace Daif\ChromePdfBundle\Builder\Behaviors;

use Daif\ChromePdfBundle\Builder\Attributes\WithConfigurationNode;
use Daif\ChromePdfBundle\Builder\BodyBag;
use Daif\ChromePdfBundle\Builder\Util\ValidatorFactory;
use Daif\ChromePdfBundle\Enumeration\SplitMode;
use Daif\ChromePdfBundle\NodeBuilder\BooleanNodeBuilder;
use Daif\ChromePdfBundle\NodeBuilder\NativeEnumNodeBuilder;
use Daif\ChromePdfBundle\NodeBuilder\ScalarNodeBuilder;

/**
 * Requires qpdf. Several resulting files are returned as a ZIP archive.
 *
 * @package Behavior\\Split
 */
trait SplitTrait
{
    abstract protected function getBodyBag(): BodyBag;

    /**
     * Either intervals or pages.
     *
     * @example splitMode(SplitMode::Intervals)
     */
    #[WithConfigurationNode(new NativeEnumNodeBuilder('split_mode', enumClass: SplitMode::class))]
    public function splitMode(SplitMode|null $splitMode = null): static
    {
        if (!$splitMode) {
            $this->getBodyBag()->unset('splitMode');
        } else {
            $this->getBodyBag()->set('splitMode', $splitMode);
        }

        return $this;
    }

    /**
     * Either the intervals or the page ranges to extract, depending on the selected mode.
     *
     * @example splitSpan('1')
     */
    #[WithConfigurationNode(new ScalarNodeBuilder('split_span'))]
    public function splitSpan(string $splitSpan): static
    {
        ValidatorFactory::splitSpan($splitSpan);
        $this->getBodyBag()->set('splitSpan', $splitSpan);

        return $this;
    }

    /**
     * Specify whether to put extracted pages into a single file or as many files as there are page ranges. Only works with pages mode. (default false).
     *
     * @example splitUnify() // is same as `->splitUnify(true)`
     */
    #[WithConfigurationNode(new BooleanNodeBuilder('split_unify'))]
    public function splitUnify(bool $bool = true): static
    {
        $this->getBodyBag()->set('splitUnify', $bool);

        return $this;
    }
}
